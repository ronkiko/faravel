<?php // v0.4.1
/* bootstrap/app.php
Purpose: Тонкий bootstrap Faravel: базовый init, создание Application, фиксация инстанса,
         затем регистрация провайдеров, загрузка маршрутов и boot — строго по порядку.
FIX: Убрали «бизнес-логику» из бутстрапа — все шаги выполняются методами Application.
*/

use Faravel\Foundation\Application;
use Faravel\Support\Facades\Facade;

// 1) Корень проекта через __DIR__/dirname — без base_path() в бутстрапе
$root = dirname(__DIR__);

// 2) Базовые init (как раньше, порядок сохранён)
require_once $root . '/framework/init.php';
require_once $root . '/framework/helpers.php';
require_once $root . '/app/init.php';

// 3) Создаём приложение и фиксируем его как текущий инстанс
$app = new Application($root);
if (method_exists(Application::class, 'setInstance')) {
    Application::setInstance($app);
}
Facade::setApplication($app);

// 4) Жизненный цикл: провайдеры → маршруты → boot
$app->registerConfiguredProviders();
$app->loadRoutes();
$app->boot();

return $app;
