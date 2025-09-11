<?php // v0.4.3
/* public/install/index.php
Purpose: Веб-установщик Faravel с тремя шагами (форма → процессинг → результат).
FIX: Реализованы 3 шага, ENV-prefill, безопасная работа с существующей БД (drop/keep),
     миграции и сиды через ядро Faravel; минималистичный UI без JS.
*/

declare(strict_types=1);

// --- Minimal bootstrap for installer (no full app boot) ---------------------
$root = dirname(__DIR__, 2);
require_once $root . '/framework/init.php';
require_once $root . '/framework/helpers.php';

// We use lightweight DB admin helpers (no app container required)
require_once $root . '/framework/Faravel/Database/DatabaseAdmin.php';

use Faravel\Support\Env;
use Faravel\Database\Database;
use Faravel\Database\DatabaseAdmin;

// --- Guards -----------------------------------------------------------------
if (file_exists($root . '/installed.lock')) {
    http_response_code(409);
    echo layout('Уже установлено', '<p>Faravel уже установлен. Удалите <code>installed.lock</code> для ' .
        'повторной установки.</p>');
    exit;
}

// --- Routing by step ---------------------------------------------------------
$step = (string)($_GET['step'] ?? '1');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $step = (string)($_POST['step'] ?? $step);
}

// Normalize step
if (!in_array($step, ['1','2','3'], true)) { $step = '1'; }

// --- Controller-ish flow -----------------------------------------------------
switch ($step) {
    case '1':
        echo render_step1();
        break;
    case '2':
        echo render_step2();
        break;
    case '3':
        echo render_step3();
        break;
    default:
        echo render_step1();
}

/**
 * Рендерит Шаг 1: форму конфигурации подключения к БД и базовых настроек.
 * Значения предзаполняются из переменных окружения, если они есть.
 *
 * @return string HTML содержимое страницы шага.
 */
function render_step1(): string
{
    $vals = [
        'DB_DRIVER'   => env('DB_DRIVER', 'mysql'),
        'DB_HOST'     => env('DB_HOST', ''),
        'DB_PORT'     => env('DB_PORT', ''),
        'DB_DATABASE' => env('DB_DATABASE', ''),
        'DB_USERNAME' => env('DB_USERNAME', ''),
        'DB_PASSWORD' => env('DB_PASSWORD', ''),
        'DB_CHARSET'  => env('DB_CHARSET', 'utf8mb4'),
        'DB_COLLATION'=> env('DB_COLLATION', 'utf8mb4_unicode_ci'),
        // Future settings (optional, persisted to .env if provided)
        'APP_NAME'    => env('APP_NAME', 'Faravel Forum'),
        'APP_LOCALE'  => env('APP_LOCALE', 'en'),
        'ADMIN_EMAIL' => env('ADMIN_EMAIL', ''),
    ];

    $content = <<<HTML
        <h1>Установка Faravel — Шаг 1/3</h1>
        <p>Заполните параметры подключения к базе данных. Если переменные окружения уже заданы,
           они подставлены автоматически.</p>

        <form method="post" action="?step=2" novalidate>
            <input type="hidden" name="step" value="2"/>

            <fieldset>
                <legend>Подключение к БД</legend>
                <label>Driver
                    <input name="DB_DRIVER" value="{$vals['DB_DRIVER']}" placeholder="mysql">
                </label>
                <label>Host
                    <input name="DB_HOST" value="{$vals['DB_HOST']}" placeholder="localhost or mysql">
                </label>
                <label>Port
                    <input name="DB_PORT" value="{$vals['DB_PORT']}" placeholder="3306">
                </label>
                <label>Database
                    <input name="DB_DATABASE" value="{$vals['DB_DATABASE']}" placeholder="forum">
                </label>
                <label>Username
                    <input name="DB_USERNAME" value="{$vals['DB_USERNAME']}" placeholder="user">
                </label>
                <label>Password
                    <input name="DB_PASSWORD" value="{$vals['DB_PASSWORD']}" type="password" placeholder="••••••••">
                </label>
                <label>Charset
                    <input name="DB_CHARSET" value="{$vals['DB_CHARSET']}" placeholder="utf8mb4">
                </label>
                <label>Collation
                    <input name="DB_COLLATION" value="{$vals['DB_COLLATION']}" placeholder="utf8mb4_unicode_ci">
                </label>
            </fieldset>

            <fieldset>
                <legend>Блок общих настроек (опционально)</legend>
                <label>Название сайта
                    <input name="APP_NAME" value="{$vals['APP_NAME']}" placeholder="Faravel Forum">
                </label>
                <label>Язык интерфейса
                    <input name="APP_LOCALE" value="{$vals['APP_LOCALE']}" placeholder="en">
                </label>
                <label>E-mail администратора
                    <input name="ADMIN_EMAIL" value="{$vals['ADMIN_EMAIL']}" placeholder="admin@example.com">
                </label>
            </fieldset>

            <div class="actions">
                <button type="submit">Продолжить →</button>
            </div>
        </form>
    HTML;

    return layout('Шаг 1 — Конфигурация', $content);
}

/**
 * Рендерит Шаг 2: подтверждение действий с существующей БД и выполнение работ:
 * создание/дроп БД, применение миграций и сидов. Поддерживает работу от ENV.
 *
 * Предусловия:
 * - Поля host/database/username должны быть заданы в POST или ENV.
 *
 * Побочные эффекты:
 * - Может создать/удалить базу данных.
 * - Инициализирует приложение (bootstrap/app.php) после подготовки БД.
 * - Может записать файл .env (если данные пришли с формы) и создать installed.lock.
 *
 * @return string HTML содержимое страницы шага.
 */
function render_step2(): string
{
    $input = array_merge($_POST, $_GET);

    // Collect DB config from POST first, fallback to ENV
    $cfg = [
        'driver'   => trim($input['DB_DRIVER']   ?? env('DB_DRIVER', 'mysql')),
        'host'     => trim($input['DB_HOST']     ?? env('DB_HOST', 'localhost')),
        'port'     => (string)($input['DB_PORT'] ?? env('DB_PORT', '3306')),
        'database' => trim($input['DB_DATABASE'] ?? env('DB_DATABASE', '')),
        'username' => trim($input['DB_USERNAME'] ?? env('DB_USERNAME', '')),
        'password' => (string)($input['DB_PASSWORD'] ?? env('DB_PASSWORD', '')),
        'charset'  => trim($input['DB_CHARSET']  ?? env('DB_CHARSET', 'utf8mb4')),
        'collation'=> trim($input['DB_COLLATION']?? env('DB_COLLATION', 'utf8mb4_unicode_ci')),
    ];

    // Validate minimal requirements
    $missing = [];
    foreach (['host','database','username'] as $k) {
        if ($cfg[$k] === '') { $missing[] = $k; }
    }
    if ($missing) {
        $msg = 'Не заполнены поля: ' . implode(', ', $missing);
        return layout('Шаг 2 — Ошибка', message_box('error', $msg) . nav_back(1));
    }

    // Detect existing database
    $exists = false;
    $errorMsg = '';
    try {
        $exists = DatabaseAdmin::databaseExists($cfg);
    } catch (\Throwable $e) {
        $errorMsg = 'Ошибка при проверке существования БД: ' . h($e->getMessage());
        return layout('Шаг 2 — Ошибка', message_box('error', $errorMsg) . nav_back(1));
    }

    // If DB exists and no decision yet — ask user
    $decision = $input['db_action'] ?? null; // 'keep' | 'drop'
    if ($exists && !$decision) {
        $hidden = hidden_inputs($cfg + [
            'step' => '2'
        ]);
        $content = <<<HTML
            <h1>Установка Faravel — Шаг 2/3</h1>
            <p>База данных <code>{$cfg['database']}</code> уже существует на
               <code>{$cfg['host']}:{$cfg['port']}</code>.</p>
            <form method="post" action="?step=2">
                {$hidden}
                <fieldset>
                    <legend>Как поступить с существующей базой?</legend>
                    <label>
                        <input type="radio" name="db_action" value="keep" required>
                        Оставить и попытаться применить миграции поверх (неразрушительно)
                    </label>
                    <label>
                        <input type="radio" name="db_action" value="drop">
                        Удалить базу и создать заново (разрушительно)
                    </label>
                </fieldset>
                <div class="actions">
                    <button type="submit">Продолжить →</button>
                    <a class="link" href="?step=1">← Назад</a>
                </div>
            </form>
        HTML;
        return layout('Шаг 2 — Подтверждение', $content);
    }

    // Ensure database state according to decision
    try {
        if ($exists && $decision === 'drop') {
            DatabaseAdmin::dropDatabase($cfg);
            $exists = false;
        }
        if (!$exists) {
            DatabaseAdmin::createDatabaseIfNotExists($cfg);
        }
    } catch (\Throwable $e) {
        $msg = 'Не удалось подготовить БД: ' . h($e->getMessage());
        return layout('Шаг 2 — Ошибка', message_box('error', $msg) . nav_back(1));
    }

    // Export to ENV for subsequent app bootstrap (without writing .env yet)
    foreach ([
        'DB_DRIVER','DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD',
        'DB_CHARSET','DB_COLLATION','APP_NAME','APP_LOCALE','ADMIN_EMAIL'
    ] as $k) {
        if (isset($input[$k])) {
            putenv($k . '=' . (string)$input[$k]);
            $_ENV[$k] = (string)$input[$k];
        }
    }

    // Bootstrap the app now (DB exists) and run migrations+seeds
    $root = dirname(__DIR__, 2);
    try {
        /** @var \Faravel\Foundation\Application $app */
        $app = require $root . '/bootstrap/app.php';

        // Prefer container 'db'
        /** @var Database $db */
        $db = $app->make(Database::class);
    } catch (\Throwable $e) {
        $msg = 'Ошибка инициализации приложения: ' . h($e->getMessage());
        return layout('Шаг 2 — Ошибка', message_box('error', $msg) . nav_back(1));
    }

    // Run migrations + seeders
    $migrationsPath = $root . '/database/migrations';
    $seedersRan = [];
    try {
        require_once $root . '/app/Console/Migrations/MigrationRunner.php';
        $runnerClass = '\\App\\Console\\Migrations\\MigrationRunner';
        if (class_exists($runnerClass)) {
            $runnerClass::run($db, $migrationsPath, 'migrate', []);
        } else {
            // Fallback: naive loader — execute each migration file
            foreach (glob($migrationsPath . '/*.php') as $file) {
                $closure = require $file;
                if (is_object($closure) && method_exists($closure, 'up')) {
                    $closure->up();
                }
            }
        }

        // Run seeders if present
        $seedDir = $root . '/database/seeders';
        if (is_dir($seedDir)) {
            foreach (glob($seedDir . '/*.php') as $file) { require_once $file; }
            if (class_exists('Database\\Seeders\\AbilitiesSeeder')) {
                (new \Database\Seeders\AbilitiesSeeder())->run();
                $seedersRan[] = 'AbilitiesSeeder';
            }
            if (class_exists('Database\\Seeders\\PerksSeeder')) {
                (new \Database\Seeders\PerksSeeder())->run();
                $seedersRan[] = 'PerksSeeder';
            }
        }
    } catch (\Throwable $e) {
        $msg = 'Миграции/сиды завершились ошибкой: ' . h($e->getMessage());
        return layout('Шаг 3 — Ошибка', message_box('error', $msg) . nav_back(1));
    }

    // Optionally persist .env (if explicitly requested by presence of POSTed fields)
    $writeEnv = !empty($input['DB_HOST']); // heuristic: user filled the form
    $envSaved = false; $envError = null;
    if ($writeEnv) {
        try {
            $pairs = [
                'DB_DRIVER','DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD',
                'DB_CHARSET','DB_COLLATION','APP_NAME','APP_LOCALE','ADMIN_EMAIL'
            ];
            $lines = [];
            foreach ($pairs as $k) {
                $v = (string)($input[$k] ?? env($k, ''));
                if ($v === '') continue;
                $lines[] = $k . '=' . $v;
            }
            if ($lines) {
                $ok = @file_put_contents($root . '/.env', implode("\n", $lines) . "\n");
                if ($ok === false) {
                    $envError = 'Не удалось записать .env (проверьте права на файл).';
                } else {
                    $envSaved = true;
                }
            }
        } catch (\Throwable $e) {
            $envError = 'Ошибка при записи .env: ' . h($e->getMessage());
        }
    }

    // Mark installed
    @file_put_contents($root . '/installed.lock', (string)time());

    // Build success page
    $seedersHtml = $seedersRan ? h(implode(', ', $seedersRan)) : '—';
    $summary = <<<HTML
        <h2>Готово!</h2>
        <p>База данных <code>{$cfg['database']}</code> создана/обновлена, миграции применены.</p>
        <ul>
            <li>Хост: <code>{$cfg['host']}:{$cfg['port']}</code></li>
            <li>Пользователь: <code>{$cfg['username']}</code></li>
            <li>Сиды: <code>{$seedersHtml}</code></li>
        </ul>
    HTML;

    $nextSteps = <<<HTML
        <h3>Дальнейшие действия</h3>
        <ol>
            <li>Удалите директорию <code>public/install</code> из продакшена.</li>
            <li>Проверьте доступ к сайту на <a href="..">главной странице</a>.</li>
            <li>Настройте роли/права в админке (при наличии).</li>
        </ol>
    HTML;

    $envNote = $envSaved
        ? '<p>.env сохранён.</p>'
        : '<p><strong>Внимание:</strong> .env не был записан автоматически. ' .
          'При необходимости задайте переменные окружения в контейнере/Docker ' .
          'или измените права на файл <code>.env</code> и повторите сохранение.</p>';

    $content = $summary . $envNote . $nextSteps;

    return layout('Шаг 3 — Успех', $content);
}

/**
 * Рендерит Шаг 3 явно, если пользователь попал на страницу напрямую.
 *
 * @return string HTML содержимое.
 */
function render_step3(): string
{
    return layout('Шаг 3', '<p>Этап завершения. Вернитесь на <a href="..">главную</a>.</p>');
}

// ========== View helpers (no JS) ============================================

/**
 * Мини-шаблон страницы с вшитым CSS без внешних зависимостей.
 *
 * @param string $title Заголовок страницы.
 * @param string $html  HTML-содержимое.
 * @return string Готовая HTML-страница.
 */
function layout(string $title, string $html): string
{
    $css = <<<CSS
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;
             margin:0;background:#f6f8fa;color:#111}
        header{background:#111;color:#fff;padding:16px 20px}
        main{max-width:880px;margin:24px auto;padding:0 16px}
        h1{font-size:22px;margin:0 0 16px}
        h2{font-size:18px;margin:16px 0 8px}
        fieldset{border:1px solid #ddd;padding:12px;margin:12px 0;background:#fff}
        label{display:block;margin:8px 0}
        input{width:100%;padding:8px;border:1px solid #ccc;border-radius:4px}
        .actions{margin-top:16px}
        button{padding:10px 14px;border:0;border-radius:6px;background:#111;color:#fff;cursor:pointer}
        a.link{margin-left:12px}
        .msg{padding:12px;border-radius:6px;margin:12px 0}
        .msg.error{background:#ffecec;border:1px solid #ffb5b5}
        .msg.ok{background:#e6ffec;border:1px solid #b6f3c1}
        code{background:#eef;border-radius:4px;padding:2px 6px}
    CSS;

    return <<<HTML
        <!doctype html>
        <html lang="ru">
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>{$title}</title>
        <style>{$css}</style>
        <body>
            <header><strong>Faravel</strong> · Установщик</header>
            <main>{$html}</main>
        </body>
        </html>
    HTML;
}

/**
 * Сообщение (успех/ошибка) в минимальном стиле.
 *
 * @param string $type 'ok'|'error'.
 * @param string $text Текст сообщения (HTML безопасен).
 * @return string HTML блока.
 */
function message_box(string $type, string $text): string
{
    $t = $type === 'error' ? 'error' : 'ok';
    return '<div class="msg ' . $t . '">' . $text . '</div>';
}

/**
 * Ссылка «Назад» к указанному шагу.
 *
 * Предусловия: $toStep ∈ {1,2}.
 *
 * @param int $toStep Номер шага.
 * @return string HTML.
 */
function nav_back(int $toStep): string
{
    $to = max(1, min(2, $toStep));
    return '<p><a class="link" href="?step=' . $to . '">← Назад</a></p>';
}

/**
 * Генерация скрытых input'ов для передачи состояния между шагами.
 *
 * @param array<string,string> $data Пары ключ/значение.
 * @return string HTML.
 */
function hidden_inputs(array $data): string
{
    $keys = [
        'step','DB_DRIVER','DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD',
        'DB_CHARSET','DB_COLLATION','APP_NAME','APP_LOCALE','ADMIN_EMAIL'
    ];
    $out = '';
    foreach ($keys as $k) {
        if (!array_key_exists($k, $data)) { continue; }
        $v = (string)$data[$k];
        $out .= '<input type="hidden" name="' . h($k) . '" value="' . h($v) . '"/>' . "\n";
    }
    return $out;
}

/**
 * HTML-экранирование.
 *
 * @param string $v Входная строка.
 * @return string Экранированная строка.
 */
function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
