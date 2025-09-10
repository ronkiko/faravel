<?php

spl_autoload_register(function ($class) {
    $base = __DIR__ . '/../';
    $file = $base . str_replace('\\', '/', $class) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});
