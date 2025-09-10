<?php // step2.php
if (!defined('INSTALL_ENTRY')) {
    http_response_code(403);
    exit('Access denied.');
}

function handleStep2() {
    $configPath = CONFIG_PATH;

    if (!file_exists($configPath)) {
        echo '<p class="error">❌ Конфигурационный файл не найден: ' . htmlspecialchars($configPath) . '</p>';
        echo '<p><a href="install.php?step=1">&larr; Вернуться к шагу 1</a></p>';
        return;
    }

    $config = Spyc::YAMLLoad($configPath);
    $db = $config['database'] ?? [];

    if (
        empty($db['DB_HOST']) ||
        empty($db['DB_USER']) ||
        empty($db['DB_NAME'])
    ) {
        echo '<p class="error">❌ В конфигурации не хватает данных подключения (DB_HOST, DB_USER, DB_NAME).</p>';
        echo '<p><a href="install.php?step=1">&larr; Вернуться к шагу 1</a></p>';
        return;
    }

    $dsnNoDb = "mysql:host={$db['DB_HOST']};charset=utf8mb4";
    $username = $db['DB_USER'];
    $password = $db['DB_PASS'] ?? '';
    $dbname = $db['DB_NAME'];
    $dropRequested = isset($_GET['drop']) && $_GET['drop'] === '1';

    try {
        $pdo = new PDO($dsnNoDb, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        if ($dropRequested) {
            echo '<p>⚙️ Удаление старой базы данных <code>' . htmlspecialchars($dbname) . '</code>...</p>';
            $pdo->exec("DROP DATABASE IF EXISTS `$dbname`");
            echo '<p class="success">✅ База данных удалена.</p>';

            echo '<p>📦 Создание новой базы данных...</p>';
            $pdo->exec("CREATE DATABASE `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            echo '<p class="success">✅ База данных создана.</p>';

            $pdo->exec("USE `$dbname`");

            echo '<h3>📥 Импорт таблиц:</h3>';
            foreach (SQL_FILES as $file) {
                $path = SQL_DIR . '/' . $file . '.sql';
                if (!file_exists($path)) {
                    echo '<p class="error">❌ Файл не найден: ' . htmlspecialchars($path) . '</p>';
                    continue;
                }
                $sql = file_get_contents($path);
                try {
                    $pdo->exec($sql);
                    echo '<p class="success">✅ ' . htmlspecialchars($file) . '.sql импортирован.</p>';
                } catch (PDOException $e) {
                    echo '<p class="error">❌ Ошибка при импорте ' . htmlspecialchars($file) . '.sql:</p>';
                    echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
                }
            }

            echo '<form method="get" action="install.php">';
            echo '<input type="hidden" name="step" value="3">';
            echo '<button type="submit">Продолжить &rarr;</button>';
            echo '</form>';
            return;
        }

        // Проверяем существование базы
        $stmt = $pdo->query("SHOW DATABASES LIKE " . $pdo->quote($dbname));
        $dbExists = $stmt->fetchColumn() !== false;

        if (!$dbExists) {
            echo '<p class="success">✅ База данных <code>' . htmlspecialchars($dbname) . '</code> будет создана.</p>';
            echo '<form method="get" action="install.php">';
            echo '<input type="hidden" name="step" value="2">';
            echo '<input type="hidden" name="drop" value="1">';
            echo '<button type="submit">Создать и продолжить &rarr;</button>';
            echo '</form>';
            return;
        }

        // Подключаемся к базе
        $pdo->exec("USE `$dbname`");

        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_NUM);

        if (!empty($tables)) {
            echo '<p class="warning">⚠️ База данных <code>' . htmlspecialchars($dbname) . '</code> уже содержит таблицы (' . count($tables) . ' найдено).</p>';
            echo '<p>Вы хотите очистить базу данных и установить заново?</p>';
            echo '<form method="get" action="install.php">';
            echo '<input type="hidden" name="step" value="2">';
            echo '<input type="hidden" name="drop" value="1">';
            echo '<button type="submit">🗑️ Очистить и продолжить &rarr;</button>';
            echo '</form>';
            echo '<p><a href="install.php?step=1">&larr; Назад</a></p>';
        } else {
            echo '<p class="success">✅ База данных существует и пуста. Можно продолжать.</p>';
            echo '<form method="get" action="install.php">';
            echo '<input type="hidden" name="step" value="2">';
            echo '<input type="hidden" name="drop" value="1">';
            echo '<button type="submit">Импортировать таблицы &rarr;</button>';
            echo '</form>';
        }
    } catch (PDOException $e) {
        echo '<p class="error">❌ Ошибка подключения к базе данных:</p>';
        echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
        echo '<p><a href="install.php?step=1">&larr; Вернуться к шагу 1</a></p>';
    }
}
