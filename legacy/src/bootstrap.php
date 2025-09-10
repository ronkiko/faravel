<?php # bootstrap.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('CONFIG_PATH', dirname(__DIR__) . '/include/config.yaml');

if (!file_exists(CONFIG_PATH)) {
    die('Форум не установлен. Перейдите в /install/install.php');
}

require dirname(__DIR__) . '/class/spyc.php';

function define_constants_from_array(array $array, string $prefix = '') {
    foreach ($array as $key => $value) {
        $constName = $prefix . $key;

        if (is_array($value)) {
            define_constants_from_array($value, $constName . '_');
        } else {
            if (!defined($constName)) {
                define($constName, $value);
            }
        }
    }
}

$config = Spyc::YAMLLoad(CONFIG_PATH);

define_constants_from_array($config['database']);
define_constants_from_array($config['paths']);
define_constants_from_array($config['sync']);

if (!defined('WWW_ROOT')) define('WWW_ROOT', dirname(__DIR__)) . '/..'; // Stub for VS
if (!defined('HTML_ROOT')) define('HTML_ROOT', __DIR__); // Stub for VS
if (!defined('SYNC_SELF')) define('SYNC_SELF', 'test'); // Stub for VS

require WWW_ROOT . '/include/functions.php';
require WWW_ROOT . '/include/db.php';
#debug($config);exit;
$groups = get_groups();
#debug($groups);


session_start();
check();

// Глобальные стили
$GLOBALS['styles'] = [];

// Эмуляция логина
#define('DEBUG_LOGIN', 1);

if (defined('DEBUG_LOGIN')) {
    define('IS_LOGGED_IN', true);
    define('USER_NAME', 'admin');
} else {
    define('IS_LOGGED_IN', isset($_SESSION['user']));
    define('USER_NAME', IS_LOGGED_IN ? $_SESSION['user']['name'] : '');
}

add_style('index');
