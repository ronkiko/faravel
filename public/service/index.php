<?php // v0.4.4
/* public/service/index.php
Purpose: Аварийно-сервисный режим (SafeMode): рецепты и диагностика/восстановление БД
         без полного бутстрапа приложения. Мини-MVC на чистом PHP, без JS.
FIX: Добавлена защита по ключу доступа через ENV SERVICEMODE_KEY.
     Требуем ?key=... при активном ключе, сохраняем авторизацию в сессии и очищаем URL.
     Встроена форма запроса ключа. Сохранение параметров формы (сессия) — как раньше.
*/

declare(strict_types=1);

// --- Minimal bootstrap (no full app boot) -----------------------------------
$root = dirname(__DIR__, 2);
require_once $root . '/framework/init.php';
require_once $root . '/framework/helpers.php';
require_once $root . '/framework/Faravel/Database/DatabaseAdmin.php';

use Faravel\Database\DatabaseAdmin;

// --- Start session for storing SafeMode form values --------------------------
if (session_status() !== PHP_SESSION_ACTIVE) {
    // Best-effort: SafeMode не зависит от контейнера, поэтому запускаем нативную сессию.
    @session_start();
}

// --- Guard: require key if SERVICEMODE_KEY is set ----------------------------

/**
 * Require SERVICEMODE_KEY (if set) before giving access to SafeMode.
 *
 * - Reads SERVICEMODE_KEY from ENV.
 * - If set and session is not authorized, checks GET 'key'.
 * - On success: stores short token in session and redirects to same URL without 'key'.
 * - On failure: renders a minimal form asking for key and stops execution.
 *
 * @return void
 */
function require_safemode_auth(): void
{
    $required = (string) env('SERVICEMODE_KEY', '');
    if ($required === '') {
        // No key required (dev/default environments).
        return;
    }

    // Already authorized in this session?
    if (!empty($_SESSION['safemode_auth']) && hash_equals($_SESSION['safemode_auth'], sha1($required))) {
        return;
    }

    // Try to authorize via GET ?key=
    $provided = (string) ($_GET['key'] ?? '');
    if ($provided !== '' && hash_equals($provided, $required)) {
        // Mark session as authorized
        $_SESSION['safemode_auth'] = sha1($required);
        @session_regenerate_id(true);

        // Clean key from URL and redirect to same path (PRG-like)
        $qs = $_GET;
        unset($qs['key']);
        $qsStr = http_build_query($qs);
        $path  = strtok((string)($_SERVER['REQUEST_URI'] ?? '/service/'), '?');
        header('Location: ' . ($path ?: '/service/') . ($qsStr ? '?' . $qsStr : ''));
        exit;
    }

    // Not authorized — render prompt (no JS)
    $wantedAction = h((string)($_GET['action'] ?? 'home'));
    $form = <<<HTML
<h1>SafeMode — доступ закрыт</h1>
<p>Для входа требуется ключ доступа. Попросите у администратора значение
   <code>SERVICEMODE_KEY</code> и откройте эту страницу с параметром <code>?key=…</code>,
   либо введите ключ в форму ниже.</p>

<form method="get" action="">
    <input type="hidden" name="action" value="{$wantedAction}">
    <fieldset>
        <legend>Ввод ключа</legend>
        <label>Ключ доступа
            <input type="password" name="key" autofocus>
        </label>
    </fieldset>
    <div class="actions">
        <button type="submit">Войти</button>
    </div>
</form>
HTML;

    echo layout('SafeMode — требуется ключ', $form);
    exit;
}

// Run the guard before any routing
require_safemode_auth();

// --- Routing -----------------------------------------------------------------
$action = (string)($_GET['action'] ?? ($_POST['action'] ?? 'home'));
if (!in_array($action, ['home','db','db_run'], true)) {
    $action = 'home';
}

// --- Controller-ish dispatch -------------------------------------------------
switch ($action) {
    case 'db':
        echo view_db_diagnostics();
        break;
    case 'db_run':
        echo handle_db_action();
        break;
    case 'home':
    default:
        echo view_home();
        break;
}

/**
 * Рендер главной страницы SafeMode: краткое объяснение + «рецепты».
 *
 * Используем nowdoc для исходников, а затем вставляем их в HTML с помощью
 * htmlspecialchars(), чтобы не было подстановок переменных и ломания разметки.
 *
 * @return string
 */
function view_home(): string
{
    // --- Code recipes (nowdoc: no interpolation) -----------------------------
    $recipe1 = <<<'CODE'
<?php
use Faravel\Database\DatabaseAdmin;

$cfg = [
    'driver'   => 'mysql',
    'host'     => 'mysql',
    'port'     => '3306',
    'database' => 'forum',
    'username' => 'user',
    'password' => 'secret',
    'charset'  => 'utf8mb4',
    'collation'=> 'utf8mb4_unicode_ci',
];

if (DatabaseAdmin::databaseExists($cfg)) {
    // keep or drop by admin's decision
} else {
    DatabaseAdmin::createDatabaseIfNotExists($cfg);
}

// now bootstrap the app and run migrations...
CODE;

    $recipe2 = <<<'CODE'
<?php
$rep = DatabaseAdmin::testReport($cfg);
if (!$rep['server_ok']) { exit("Server down\n"); }
if (!$rep['db_exists']) { DatabaseAdmin::createDatabaseIfNotExists($cfg); }
if (!$rep['connect_ok']) { exit("Cannot connect to DB\n"); }
echo "ok\n";
CODE;

    $recipe3 = <<<'CODE'
<?php
foreach ($tenants as $t) {
    $cfg['database'] = 'forum_' . $t->id;
    DatabaseAdmin::createDatabaseIfNotExists($cfg);
}
CODE;

    $recipe4 = <<<'CODE'
<?php
if (DatabaseAdmin::canConnect($cfg)) { echo "ok\n"; } else { echo "fail\n"; }
CODE;

    $intro = <<<'HTML'
<h1>SafeMode — аварийно-сервисный режим</h1>
<p>Этот режим предназначен для диагностики и восстановления, когда приложение не может
   загрузиться полностью (например, БД не создана/недоступна).</p>

<nav class="tabs">
    <a class="tab" href="?action=db">Диагностика БД</a>
</nav>

<h2>Рецепты использования DatabaseAdmin</h2>
<p>Ниже — основные сценарии. Их можно запускать вручную через эту страницу или через CLI-утилиту
   (<code>tools/recovery/db_recovery.php</code>).</p>
HTML;

    $content  = $intro;

    $content .= '<details open><summary><strong>1) Проверка/создание БД до загрузки приложения</strong></summary>';
    $content .= '<pre><code>' . htmlspecialchars($recipe1, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code></pre>';
    $content .= '</details>';

    $content .= '<details><summary><strong>2) Preflight (CI/CD): сервер, наличие БД, подключение</strong></summary>';
    $content .= '<pre><code>' . htmlspecialchars($recipe2, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code></pre>';
    $content .= '</details>';

    $content .= '<details><summary><strong>3) Мультитенант: создать БД для каждого арендатора</strong></summary>';
    $content .= '<pre><code>' . htmlspecialchars($recipe3, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code></pre>';
    $content .= '</details>';

    $content .= '<details><summary><strong>4) Health-check без контейнера</strong></summary>';
    $content .= '<pre><code>' . htmlspecialchars($recipe4, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code></pre>';
    $content .= '</details>';

    $content .= '<hr><p><em>Совет по безопасности:</em> не храните пароли в логах; используйте переменные ' .
                'окружения в контейнерах и удаляйте <code>public/service</code> из продакшена, если он ' .
                'не нужен на постоянной основе.</p>';

    return layout('SafeMode — аварийный сервис', $content);
}

/**
 * Загрузка предзаполненных значений для формы диагностики БД.
 *
 * Источник значений (приоритет у более «свежего»):
 *   1) Текущий запрос (POST/GET), если поля заданы (не пустые).
 *   2) Сессия SafeMode (если ранее сохранено).
 *   3) ENV-дефолты (DB_DRIVER/DB_HOST/…).
 *
 * @return array{
 *   driver:string,host:string,port:string,database:string,username:string,password:string,
 *   charset:string,collation:string
 * }
 */
function load_db_prefs(): array
{
    // 3) ENV defaults
    $vals = [
        'driver'   => env('DB_DRIVER', 'mysql'),
        'host'     => env('DB_HOST', ''),
               'port'     => (string)env('DB_PORT', ''),
        'database' => env('DB_DATABASE', ''),
        'username' => env('DB_USERNAME', ''),
        'password' => env('DB_PASSWORD', ''),
        'charset'  => env('DB_CHARSET', 'utf8mb4'),
        'collation'=> env('DB_COLLATION', 'utf8mb4_unicode_ci'),
    ];

    // 2) Session overlay
    if (!empty($_SESSION['safemode_db']) && is_array($_SESSION['safemode_db'])) {
        foreach ($vals as $k => $_) {
            if (isset($_SESSION['safemode_db'][$k])) {
                $vals[$k] = (string)$_SESSION['safemode_db'][$k];
            }
        }
    }

    // 1) Request overrides (POST first, then GET)
    foreach (['driver','host','port','database','username','password','charset','collation'] as $k) {
        if (isset($_POST[$k]) && $_POST[$k] !== '') {
            $vals[$k] = (string)$_POST[$k];
        } elseif (isset($_GET[$k]) && $_GET[$k] !== '') {
            $vals[$k] = (string)$_GET[$k];
        }
    }

    return $vals;
}

/**
 * Сохранение значений подключения к БД в сессию SafeMode.
 *
 * @param array{
 *   driver:string,host:string,port:string,database:string,username:string,password:string,
 *   charset:string,collation:string
 * } $cfg
 * @return void
 */
function save_db_prefs(array $cfg): void
{
    $_SESSION['safemode_db'] = [
        'driver'   => (string)($cfg['driver']   ?? 'mysql'),
        'host'     => (string)($cfg['host']     ?? ''),
        'port'     => (string)($cfg['port']     ?? ''),
        'database' => (string)($cfg['database'] ?? ''),
        'username' => (string)($cfg['username'] ?? ''),
        'password' => (string)($cfg['password'] ?? ''),
        'charset'  => (string)($cfg['charset']  ?? 'utf8mb4'),
        'collation'=> (string)($cfg['collation']?? 'utf8mb4_unicode_ci'),
    ];
}

/**
 * Сброс сохранённых значений подключения.
 *
 * Побочный эффект: очищает $_SESSION['safemode_db'].
 *
 * @return void
 */
function clear_db_prefs(): void
{
    unset($_SESSION['safemode_db']);
}

/**
 * Рендер формы диагностики БД (с предзаполнением из load_db_prefs()).
 *
 * @return string
 */
function view_db_diagnostics(): string
{
    $vals = load_db_prefs();

    $form = <<<HTML
<h1>Диагностика БД</h1>
<form method="post" action="?action=db_run" novalidate>
    <fieldset>
        <legend>Параметры подключения</legend>
        <label>Driver
            <input name="driver" value="{$vals['driver']}" placeholder="mysql">
        </label>
        <label>Host
            <input name="host" value="{$vals['host']}" placeholder="localhost or mysql">
        </label>
        <label>Port
            <input name="port" value="{$vals['port']}" placeholder="3306">
        </label>
        <label>Database
            <input name="database" value="{$vals['database']}" placeholder="forum">
        </label>
        <label>Username
            <input name="username" value="{$vals['username']}" placeholder="user">
        </label>
        <label>Password
            <input name="password" type="password" value="{$vals['password']}" placeholder="••••••••">
        </label>
        <label>Charset
            <input name="charset" value="{$vals['charset']}" placeholder="utf8mb4">
        </label>
        <label>Collation
            <input name="collation" value="{$vals['collation']}" placeholder="utf8mb4_unicode_ci">
        </label>
    </fieldset>

    <div class="actions">
        <button name="op" value="ping" type="submit">Ping сервера</button>
        <button name="op" value="exists" type="submit">Проверить БД</button>
        <button name="op" value="create" type="submit">Создать БД</button>
        <button name="op" value="drop" type="submit">Удалить БД</button>
        <button name="op" value="connect" type="submit">Проверка полного подключения</button>
        <button name="op" value="clear" type="submit" title="Очистить сохранённые значения">Сбросить значения</button>
        <a class="link" href="?action=home">← Рецепты</a>
    </div>
</form>
HTML;

    return layout('SafeMode — Диагностика БД', $form);
}

/**
 * Обработчик действий диагностики БД, с сохранением введённых значений в сессию.
 *
 * @return string
 */
function handle_db_action(): string
{
    // Берём то, что пришло сейчас; если полей нет — подставим сохранённые/ENV.
    $cfg = load_db_prefs();

    // Если пользователь отправил форму — это «самая свежая» версия; сохраним её.
    if (!empty($_POST)) {
        save_db_prefs($cfg);
    }

    $op  = (string)($_POST['op'] ?? '');

    // Спец-кейс: очистка сохранённых значений
    if ($op === 'clear') {
        clear_db_prefs();
        return layout('SafeMode — Результат', message('ok', 'Сохранённые значения сброшены.') .
            '<p><a class="link" href="?action=db">← Назад к диагностике</a></p>');
    }

    $start = microtime(true);
    $html  = '<h1>Результат действия</h1>';
    try {
        if ($op === 'ping') {
            $elapsed = DatabaseAdmin::pingServer($cfg, 3000);
            $html .= message('ok', 'Сервер доступен, время подключения: ~' . number_format($elapsed*1000, 1) . ' ms.');
        } elseif ($op === 'exists') {
            $exists = DatabaseAdmin::databaseExists($cfg);
            $html .= message('ok', 'База ' . h($cfg['database']) . ' ' . ($exists ? 'существует' : 'не найдена') . '.');
        } elseif ($op === 'create') {
            DatabaseAdmin::createDatabaseIfNotExists($cfg);
            $html .= message('ok', 'База ' . h($cfg['database']) . ' создана (или уже существовала).');
        } elseif ($op === 'drop') {
            DatabaseAdmin::dropDatabase($cfg);
            $html .= message('ok', 'База ' . h($cfg['database']) . ' удалена (если существовала).');
        } elseif ($op === 'connect') {
            $rep = DatabaseAdmin::testReport($cfg);
            $html .= render_report($rep);
        } else {
            $html .= message('error', 'Неизвестная операция.');
        }
    } catch (\Throwable $e) {
        $html .= message('error', 'Ошибка: ' . h($e->getMessage()));
    }
    $elapsedPage = microtime(true) - $start;
    $html .= '<p><small>Время выполнения страницы: ~' . number_format($elapsedPage*1000, 1) . ' ms.</small></p>';
    $html .= '<p><a class="link" href="?action=db">← Назад к диагностике</a></p>';

    return layout('SafeMode — Результат', $html);
}

/**
 * Рендер таблицы отчёта по подключению.
 *
 * @param array{server_ok:bool, elapsed:float, db_exists:bool|null, connect_ok:bool|null, error:?string} $rep
 * @return string
 */
function render_report(array $rep): string
{
    $rows = [
        ['Сервер доступен', $rep['server_ok'] ? 'да' : 'нет'],
        ['Время открытия соединения', number_format($rep['elapsed']*1000, 1) . ' ms'],
        ['База существует', $rep['db_exists'] === null ? '—' : ($rep['db_exists'] ? 'да' : 'нет')],
        ['Подключение к базе', $rep['connect_ok'] === null ? '—' : ($rep['connect_ok'] ? 'ok' : 'fail')],
    ];
    $tbl = '<table class="kv">';
    foreach ($rows as [$k,$v]) {
        $tbl .= '<tr><th>' . h($k) . '</th><td>' . h((string)$v) . '</td></tr>';
    }
    $tbl .= '</table>';
    if (!empty($rep['error'])) {
        $tbl .= message('error', 'Детали: ' . h((string)$rep['error']));
    }
    return $tbl;
}

/**
 * Каркас страницы с минимальным встроенным CSS.
 *
 * @param string $title Заголовок страницы.
 * @param string $html  HTML-контент.
 * @return string
 */
function layout(string $title, string $html): string
{
    $css = <<<'CSS'
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;margin:0;background:#f6f8fa;color:#111}
header{background:#0f172a;color:#fff;padding:14px 18px}
main{max-width:960px;margin:24px auto;padding:0 16px}
h1{font-size:22px;margin:0 0 12px}
h2{font-size:18px;margin:18px 0 8px}
fieldset{border:1px solid #e5e7eb;padding:12px;margin:12px 0;background:#fff;border-radius:8px}
label{display:block;margin:8px 0}
input{width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px}
.actions{margin-top:12px;display:flex;flex-wrap:wrap;gap:8px}
button{padding:10px 14px;border:0;border-radius:8px;background:#0f172a;color:#fff;cursor:pointer}
a.link{padding:10px 12px;border-radius:8px;background:#e5e7eb;color:#111;text-decoration:none}
.msg{padding:12px;border-radius:8px;margin:12px 0}
.msg.ok{background:#e6ffec;border:1px solid #b6f3c1}
.msg.error{background:#ffecec;border:1px solid #ffb5b5}
nav.tabs{margin:8px 0 16px}
.tab{display:inline-block;margin-right:8px;padding:8px 12px;border-radius:8px;background:#e5e7eb;color:#111;text-decoration:none}
table.kv{border-collapse:collapse;width:100%;background:#fff;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden}
table.kv th, table.kv td{padding:8px 10px;border-bottom:1px solid #e5e7eb;text-align:left}
details{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:10px 12px;margin:10px 0}
summary{cursor:pointer}
CSS;

    return <<<HTML
<!doctype html>
<html lang="ru">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$title}</title>
<style>{$css}</style>
<body>
  <header><strong>Faravel</strong> · SafeMode</header>
  <main>{$html}</main>
</body>
</html>
HTML;
}

/**
 * Маленький блок сообщения (ok|error).
 *
 * @param 'ok'|'error' $type
 * @param string $text
 * @return string
 */
function message(string $type, string $text): string
{
    $t = $type === 'error' ? 'error' : 'ok';
    return '<div class="msg ' . $t . '">' . $text . '</div>';
}

/**
 * HTML-экранирование.
 *
 * @param string $v
 * @return string
 */
function h(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
