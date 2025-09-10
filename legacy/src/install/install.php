<?php
// /install/install.php
define('INSTALL_ENTRY', true);

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Пути к конфигурации и SQL
define('CONFIG_PATH', dirname(__DIR__) . '/../include/config.yaml');
define('DEFAULTS_PATH', __DIR__ . '/defaults.yaml');
define('SQL_DIR', __DIR__ . '/sql');
define('SQL_FILES', [
    'events',
    'categories',
    'roles',
    'groups',
    'users'
]);

require_once dirname(__DIR__) . '/../class/spyc.php';

if (!file_exists(DEFAULTS_PATH)) {
    exit("Ошибка: не найден файл конфигурации по умолчанию: " . DEFAULTS_PATH . "\n");
}

foreach (SQL_FILES as $sqlFile) {
    $path = SQL_DIR . '/' . $sqlFile . '.sql';
    if (!file_exists($path)) {
        exit("Ошибка: отсутствует SQL файл: $path\n");
    }
}

$headerPath = __DIR__ . '/template/header.php';
$footerPath = __DIR__ . '/template/footer.php';

if (!file_exists($headerPath)) {
    exit("Ошибка: отсутствует файл шаблона header: $headerPath\n");
}
if (!file_exists($footerPath)) {
    exit("Ошибка: отсутствует файл шаблона footer: $footerPath\n");
}

function redirect($page) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Redirecting...</title><meta http-equiv="refresh" content="0;url=install.php?'.$page.'"></head><body></body></html>';
    exit;
}

session_start();

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

require_once $headerPath;

switch ($step) {
    case 1:
        require_once __DIR__ . '/step1.php';
        handleStep1();
        break;
    case 2:
        require_once __DIR__ . '/step2.php';
        handleStep2();
        break;
    case 3:
        require_once __DIR__ . '/step3.php';
        handleStep3();
        break;
    default:
        echo '<p>Неверный шаг установки.</p>';
        break;
}

require_once $footerPath;
