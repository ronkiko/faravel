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
// Подключаем автолоадер приложения, чтобы модули SafeMode могли находить классы
// из пространства имён App\ (например, App\Services\Admin\ContractChecker).  В ранних
// версиях админки загрузка выполнялась без инициализации autoload, что приводило к
// фатальным ошибкам «Class ... not found».  Этот вызов регистрирует PSR‑4
// автозагрузчик для App\ и Database\.
require_once $root . '/app/init.php';
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
            $error = 'Ключ не задан в ENV: ADMIN_KEY (или SERVICEMODE_KEY).';
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
// Список разрешённых страниц SafeMode.  Расширяемый: новые модули
// подключаются через ключ => файл.  Стандартные модули: home, service, install.
// Новые модули в v0.4.117: stack — проверка соответствия контрактам;
// checksum — контроль хеш‑сумм файлов.
$allowedPages = [
    'home'     => '_home.php',     // демо-модуль
    'service'  => '_service.php',  // проверка БД
    'install'  => '_install.php',  // инсталлятор
    'stack'    => '_stack.php',    // проверка слоёв стека
    'checksum' => '_checksum.php', // контроль целостности файлов
];

// Активная страница
$page = (string)($_GET['page'] ?? 'home');
if (!array_key_exists($page, $allowedPages)) {
    $page = 'home';
}

// Сборка меню (имя → ссылка)
// Построение меню (название → ссылка). Новые пункты: проверка стека и контроль файлов.
$menu = [
    'Панель'          => 'index.php?page=home',
    'Проверки БД'     => 'index.php?page=service',
    'Инсталлятор'     => 'index.php?page=install',
    'Проверка стека'  => 'index.php?page=stack',
    'Контроль файлов' => 'index.php?page=checksum',
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
