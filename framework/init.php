<?php # Faravel/init
// Только автозагрузка Faravel
spl_autoload_register(function ($class) {
    $prefix = 'Faravel\\';
    $baseDir = __DIR__ . '/Faravel/';
    $len = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) return;

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
