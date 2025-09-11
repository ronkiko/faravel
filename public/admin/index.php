<?php // v0.4.1
/* public/admin/index.php
Назначение: единая точка входа SafeMode-админки (ключ → сессия → роутинг модулей).
FIX: начальная версия админки: ADMIN_ENTRY, сессионная авторизация по ключу,
     лайаут (шапка/сайдбар/контент), белый список страниц и защита модулей.
*/

declare(strict_types=1);

define('ADMIN_ENTRY', 1);

// Минимальный бутстрап проекта (автозагрузка, env, хелперы); без поднятия всего ядра.
$root = realpath(__DIR__ . '/../../');
require_once $root . '/framework/init.php';
require_once $root . '/framework/helpers.php';
require_once __DIR__ . '/_helpers.php';

// Старт сессии до любого вывода.
admin_session_start();

// Обработка выхода.
if (isset($_GET['logout'])) {
    admin_logout();
    admin_redirect('index.php');
}

// Ключ админки: SERVICEMODE_KEY приоритетно, иначе ADMIN_KEY.
$key = admin_resolve_key();

// Авторизация по ключу (однократно: флаг ложится в сессию).
if (!admin_is_authorized()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $posted = (string)($_POST['admin_key'] ?? '');
        if ($key === '') {
            $error = 'Ключ не задан в ENV: SERVICEMODE_KEY или ADMIN_KEY.';
        } elseif (!hash_equals($key, $posted)) {
            $error = 'Неверный ключ.';
        } else {
            admin_mark_authorized();
            admin_redirect('index.php');
        }
    }

    // Экран входа
    admin_render_login($key !== '', $error ?? null);
    exit;
}

// Авторизованы — рисуем каркас и встраиваем выбранный модуль.
$allowedPages = [
    'home'    => '_home.php',     // демо-модуль, есть в этом коммите
    'service' => '_service.php',  // появится на шаге 2
    'install' => '_install.php',  // появится на шаге 3
];

// Активная страница
$page = (string)($_GET['page'] ?? 'home');
if (!array_key_exists($page, $allowedPages)) {
    $page = 'home';
}

// Сборка меню (имя → ссылка)
$menu = [
    'Панель'         => 'index.php?page=home',
    'Проверки БД'    => 'index.php?page=service',
    'Инсталлятор'    => 'index.php?page=install',
];

// Рендер каркаса и врезка модуля
admin_layout_start('Faravel SafeMode Admin', $menu, $page);

$modulePath = __DIR__ . '/' . $allowedPages[$page];
if (!is_file($modulePath)) {
    admin_alert('info', 'Модуль ещё не установлен. Будет добавлен на следующем шаге реализации.');
} else {
    /** @psalm-suppress UnresolvableInclude */
    require $modulePath;
}

admin_layout_end();
