<?php // v0.4.118
/* public/safemode/index.php
Назначение: аварийный режим SafeMode, перенесённый в отдельный URL `/safemode/`.
   Этот режим позволяет выполнять сервисные операции (проверки БД, инсталлятор,
   проверка стека и контроль файлов) без загрузки основного ядра.  Страница
   использует минимальный бутстрап: инициализация окружения, загрузка
   автозагрузчика приложения и подключение вспомогательных функций, используемых
   старой админкой.  Все модули подключаются из каталога `public/admin`, что
   позволяет избежать дублирования кода.

   FIX: Новый файл. SafeMode вынесен из `/admin/` в отдельный маршрут `/safemode/`,
   чтобы основной `/admin/` стал частью полноценной MVC‑админки.  Здесь
   реализована та же логика проверки ключа и выбор модулей, что и в старой
   версии админки, но с подключённым автозагрузчиком для корректной работы
   сервисов App\Services\Admin\*.
*/

declare(strict_types=1);

// Используем ту же константу, что и старая админка.  Модули SafeMode
// проверяют её для предотвращения прямого доступа.
define('ADMIN_ENTRY', 1);

// Минимальный бутстрап: init, helpers и автолоадер приложения.  Нам не
// нужен полный kernel, поэтому не подгружаем и не инициализируем сервисы
// MVC.  Однако автолоадер App необходим для таких классов, как
// App\Services\Admin\ContractChecker и ChecksumService.
$root = realpath(__DIR__ . '/../../');
require_once $root . '/framework/init.php';
require_once $root . '/framework/helpers.php';
require_once $root . '/app/init.php';

// Функции для работы с сессией, шаблоном и выводом сообщений берём из
// существующего файла `public/admin/_helpers.php`.  Это позволяет
// переиспользовать код и поддерживать единый вид.
require_once __DIR__ . '/../admin/_helpers.php';

// Запускаем сессию до любого вывода, чтобы авторизация по ключу работала.
admin_session_start();

// Обрабатываем запрос на выход: удаляем флаг авторизации и перегружаем страницу.
if (isset($_GET['logout'])) {
    admin_logout();
    admin_redirect('index.php');
}

// Определяем ключ админки: в приоритете `ADMIN_KEY`, затем `SERVICEMODE_KEY`.
$key = admin_resolve_key();

// Авторизация по ключу: при первой загрузке показываем форму ввода ключа.
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
    admin_render_login($key !== '', $error ?? null);
    exit;
}

// Карта допустимых страниц SafeMode.  Пути относительны текущему файлу.
$allowedPages = [
    'home'     => '../admin/_home.php',
    'service'  => '../admin/_service.php',
    'install'  => '../admin/_install.php',
    'stack'    => '../admin/_stack.php',
    'checksum' => '../admin/_checksum.php',
];

// Определяем выбранную страницу (по умолчанию 'home').
$page = (string)($_GET['page'] ?? 'home');
if (!array_key_exists($page, $allowedPages)) {
    $page = 'home';
}

// Формируем меню: имя → ссылка.  При добавлении новых модулей
// обновите этот массив.
$menu = [
    'Панель'          => 'index.php?page=home',
    'Проверки БД'     => 'index.php?page=service',
    'Инсталлятор'     => 'index.php?page=install',
    'Проверка стека'  => 'index.php?page=stack',
    'Контроль файлов' => 'index.php?page=checksum',
];

// Рендерим заголовок, меню и начинаем вывод контента.
admin_layout_start('Faravel SafeMode Admin', $menu, $page);

// Подключаем выбранный модуль.
$modulePath = __DIR__ . '/' . $allowedPages[$page];
if (!is_file($modulePath)) {
    admin_alert('info', 'Модуль ещё не установлен. Будет добавлен на следующем шаге реализации.');
} else {
    /** @psalm-suppress UnresolvableInclude */
    require $modulePath;
}

// Завершаем вывод страницы.
admin_layout_end();