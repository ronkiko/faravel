<?php # index.php

use Faravel\Support\Facades\DB;
use Faravel\Http\Response;

// Создание и подготовка приложения
$app = require_once __DIR__ . '/../bootstrap/app.php';

// Запуск HTTP-ядра
$kernel = new App\Http\Kernel($app);

$response = $kernel->handle();

// Отправка ответа
if ($response instanceof Response) {
    $response->send();
} else {
    echo (string)$response;
}
