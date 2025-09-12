<?php // v0.4.9
/* public/admin/_install.php
Purpose: Модуль «Инсталлятор» в SafeMode-админке. Подготовка БД (create/drop), проверка
         подключения, миграции/сиды через framework/migrator.php, управление installed.lock.
FIX: После create/drop выполняется верификация состояния через DatabaseAdmin::databaseExists().
     Сообщения стали правдивыми: «создана/не создана», «удалена/не удалена». В "Проверить
     подключение" добавлен подробный отчёт DatabaseAdmin::testReport().
*/

declare(strict_types=1);

if (!defined('ADMIN_ENTRY')) {
    // Direct access protection: only via /admin/index.php.
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

use Faravel\Database\DatabaseAdmin as DBA;

/**
 * Read DB config from POST with ENV fallbacks (same rules as in service module).
 *
 * @return array{
 *   driver:string, host:string, port:string, database:string, username:string, password:string
 * }
 */
function install_db_config(): array
{
    $env = static function (string $k, string $def = ''): string {
        if (function_exists('env')) {
            $v = env($k);
            return $v !== null ? (string)$v : $def;
        }
        $v = getenv($k);
        return $v !== false ? (string)$v : $def;
    };

    $driver   = (string)($_POST['driver']   ?? $env('DB_DRIVER', 'mysql'));
    $host     = (string)($_POST['host']     ?? $env('DB_HOST', '127.0.0.1'));
    $port     = (string)($_POST['port']     ?? $env('DB_PORT', '3306'));
    $database = (string)($_POST['database'] ?? $env('DB_DATABASE', 'faravel'));
    $username = (string)($_POST['username'] ?? $env('DB_USERNAME', 'root'));
    $password = (string)($_POST['password'] ?? $env('DB_PASSWORD', ''));

    return [
        'driver'   => trim($driver),
        'host'     => trim($host),
        'port'     => trim($port),
        'database' => trim($database),
        'username' => trim($username),
        'password' => $password,
    ];
}

/**
 * Подключить единый миграционный раннер и отдать обработчики.
 *
 * @param string $root Абсолютный корень проекта.
 * @return array{migrate:callable|null, seed:callable|null}
 */
function install_try_load_runner(string $root): array
{
    $runner = $root . '/framework/migrator.php';
    if (is_file($runner)) {
        /** @psalm-suppress UnresolvableInclude */
        require_once $runner;
    }

    $migrate = function_exists('faravel_migrate_all') ? 'faravel_migrate_all' : null;
    $seed    = function_exists('faravel_seed_all')    ? 'faravel_seed_all'    : null;

    return ['migrate' => $migrate, 'seed' => $seed];
}

/**
 * Возвращает абсолютный путь до installed.lock в public/.
 *
 * @param string $root Абсолютный корень проекта.
 * @return string Абсолютный путь к файлу installed.lock.
 */
function install_lock_path(string $root): string
{
    return rtrim($root, '/\\') . '/public/installed.lock';
}

/**
 * Проверяет наличие installed.lock.
 *
 * @param string $root Абсолютный корень проекта.
 * @return bool true, если файл существует.
 */
function install_lock_exists(string $root): bool
{
    return is_file(install_lock_path($root));
}

/**
 * Создаёт installed.lock в public/ с простым содержимым и датой.
 *
 * @param string $root Абсолютный корень проекта.
 * @return array{ok:bool,message:string}
 */
function install_lock_create(string $root): array
{
    $path = install_lock_path($root);
    $dir  = dirname($path);

    if (!is_dir($dir)) {
        return ['ok' => false, 'message' => 'Папка public/ не найдена: ' . $dir];
    }
    if (!is_writable($dir)) {
        return ['ok' => false, 'message' => 'Нет прав на запись в: ' . $dir];
    }

    $content = "Faravel installed lock\ncreated_at=" . date('c') . "\n";
    $written = @file_put_contents($path, $content);

    if ($written === false) {
        return ['ok' => false, 'message' => 'Не удалось создать файл: ' . $path];
    }
    return ['ok' => true, 'message' => 'Файл создан: ' . $path];
}

/**
 * Удаляет installed.lock.
 *
 * @param string $root Абсолютный корень проекта.
 * @return array{ok:bool,message:string}
 */
function install_lock_delete(string $root): array
{
    $path = install_lock_path($root);
    if (!is_file($path)) {
        return ['ok' => false, 'message' => 'Файл не найден: ' . $path];
    }
    if (!is_writable($path)) {
        return ['ok' => false, 'message' => 'Нет прав на удаление: ' . $path];
    }
    $ok = @unlink($path);
    return ['ok' => (bool)$ok, 'message' => $ok ? 'Удалён: ' . $path : 'Не удалось удалить файл.'];
}

/**
 * Render DB config form and installer controls.
 *
 * @param array{
 *   driver:string, host:string, port:string, database:string, username:string, password:string
 * } $cfg
 * @param bool $runnerAvailable Whether migrate/seed runner was found.
 * @param bool $lockExists      Whether installed.lock exists in public/.
 * @return void
 */
function install_render_form(array $cfg, bool $runnerAvailable, bool $lockExists): void
{
    echo '<div class="panel"><div class="hd"><strong>Инсталлятор</strong></div><div class="bd">';
    echo '<form method="post" class="inline" autocomplete="off">';
    echo '<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px">';
    echo '<label>Driver <input name="driver" value="' . htmlspecialchars($cfg['driver']) . '"></label>';
    echo '<label>Host <input name="host" value="' . htmlspecialchars($cfg['host']) . '"></label>';
    echo '<label>Port <input name="port" value="' . htmlspecialchars($cfg['port']) . '"></label>';
    echo '<label>Database <input name="database" value="' . htmlspecialchars($cfg['database']) . '"></label>';
    echo '<label>User <input name="username" value="' . htmlspecialchars($cfg['username']) . '"></label>';
    echo '<label>Password <input type="password" name="password" value="'
        . htmlspecialchars($cfg['password']) . '"></label>';
    echo '</div>';

    echo '<div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">';
    echo '<button name="action" value="connect">Проверить подключение</button>';
    echo '<label style="margin-left:16px">'
        . '<input type="checkbox" name="confirm" value="1"> Подтверждаю операции CREATE/DROP'
        . '</label>';
    echo '<button name="action" value="create">Создать БД</button>';
    echo '<button name="action" value="drop">Удалить БД</button>';
    echo '</div>';

    echo '<hr style="margin:14px 0;border:none;border-top:1px solid #eee">';

    if ($runnerAvailable) {
        echo '<div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">';
        echo '<button name="action" value="migrate">Применить миграции</button>';
        echo '<button name="action" value="seed">Выполнить сиды</button>';
        echo '<button name="action" value="fresh">Выполнить Fresh (drop + migrate + seed)</button>';
        echo '</div>';
    } else {
        admin_alert(
            'error',
            'Миграционный раннер не найден (framework/migrator.php). Доступны только '
            . 'операции create/drop/connect.'
        );
        echo '<p class="muted">Подключите раннер или используйте CLI для миграций/сидов.</p>';
    }

    echo '<hr style="margin:14px 0;border:none;border-top:1px solid #eee">';

    echo '<div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">';
    if ($lockExists) {
        admin_alert('info', 'installed.lock существует в public/.');
        echo '<label style="margin-left:16px">'
           . '<input type="checkbox" name="lock_confirm" value="1"> Подтверждаю удаление lock'
           . '</label>';
        echo '<button name="action" value="lock_delete">Удалить installed.lock</button>';
    } else {
        echo '<button name="action" value="lock_create">Создать installed.lock</button>';
        echo '<span class="muted">Создание файла блокирует повторный запуск инсталлятора.</span>';
    }
    echo '</div>';

    echo '</form></div></div>';
}

/**
 * Handle install actions using DatabaseAdmin and the runner.
 *
 * Почему так: слой Controller для инсталлятора. После create/drop сразу перепроверяем
 * фактическое состояние БД и возвращаем подробности диагностики.
 *
 * @param string $action One of: connect|create|drop|migrate|seed|fresh|lock_create|lock_delete
 * @param array{
 *   driver:string, host:string, port:string, database:string, username:string, password:string
 * } $cfg
 * @param array{migrate:callable|null, seed:callable|null} $runner
 * @param bool   $confirm   Confirm flag for CREATE/DROP/Fresh.
 * @param string $root      Absolute project root.
 * @param bool   $lockConf  Confirm flag for deleting installed.lock.
 * @return array{ok:bool,title:string,message:string,details?:mixed}
 */
function install_handle_action(
    string $action,
    array $cfg,
    array $runner,
    bool $confirm,
    string $root,
    bool $lockConf
): array {
    try {
        switch ($action) {
            case 'connect': {
                // Detailed diagnostic instead of a simple boolean.
                $rep = DBA::testReport($cfg);
                $msg = [];
                $msg[] = $rep['server_ok'] ? 'Сервер доступен.' : 'Сервер недоступен.';
                if ($rep['db_exists'] === true)   { $msg[] = 'БД существует.'; }
                if ($rep['db_exists'] === false)  { $msg[] = 'БД не существует.'; }
                if ($rep['connect_ok'] === true)  { $msg[] = 'Подключение к БД успешно.'; }
                if ($rep['connect_ok'] === false) { $msg[] = 'Подключиться к БД не удалось.'; }
                if (!empty($rep['error']))        { $msg[] = 'Ошибка: ' . $rep['error']; }

                return [
                    'ok'      => $rep['server_ok'] && ($rep['connect_ok'] ?? true),
                    'title'   => 'Проверка подключения',
                    'message' => implode(' ', $msg),
                    'details' => $rep,
                ];
            }

            case 'create': {
                if (!$confirm) {
                    return [
                        'ok' => false,
                        'title' => 'Создание БД',
                        'message' => 'Требуется подтверждение операции.',
                    ];
                }
                DBA::createDatabaseIfNotExists($cfg);
                // Verify state
                $exists = DBA::databaseExists($cfg);
                $rep = DBA::testReport($cfg);

                return [
                    'ok'      => $exists,
                    'title'   => 'Создание БД',
                    'message' => $exists
                        ? 'База создана или уже существовала.'
                        : 'Команда выполнена, но БД не найдена. Проверьте привилегии пользователя.',
                    'details' => $rep,
                ];
            }

            case 'drop': {
                if (!$confirm) {
                    return [
                        'ok' => false,
                        'title' => 'Удаление БД',
                        'message' => 'Требуется подтверждение операции.',
                    ];
                }
                DBA::dropDatabase($cfg);
                // Verify state
                $exists = DBA::databaseExists($cfg);
                $rep = DBA::testReport($cfg);

                return [
                    'ok'      => !$exists,
                    'title'   => 'Удаление БД',
                    'message' => !$exists
                        ? 'База удалена (или не существовала).'
                        : 'Команда выполнена, но БД всё ещё существует. Проверьте привилегии.',
                    'details' => $rep,
                ];
            }

            case 'migrate': {
                if (!$runner['migrate']) {
                    return [
                        'ok' => false,
                        'title' => 'Миграции',
                        'message' => 'Раннер миграций не подключён (framework/migrator.php).',
                    ];
                }
                /** @var callable $call */
                $call = $runner['migrate'];
                $res = $call();
                return [
                    'ok' => empty($res['errors'] ?? []),
                    'title' => 'Миграции',
                    'message' => empty($res['errors'] ?? [])
                        ? 'Миграции выполнены.'
                        : 'Миграции завершились с ошибками.',
                    'details' => $res,
                ];
            }

            case 'seed': {
                if (!$runner['seed']) {
                    return [
                        'ok' => false,
                        'title' => 'Сиды',
                        'message' => 'Раннер сидов не подключён (framework/migrator.php).',
                    ];
                }
                /** @var callable $call */
                $call = $runner['seed'];
                $res = $call();
                return [
                    'ok' => empty($res['errors'] ?? []),
                    'title' => 'Сиды',
                    'message' => empty($res['errors'] ?? [])
                        ? 'Сиды выполнены.'
                        : 'Сиды завершились с ошибками.',
                    'details' => $res,
                ];
            }

            case 'fresh': {
                if (!$confirm) {
                    return [
                        'ok' => false,
                        'title' => 'Fresh',
                        'message' => 'Отсутствует подтверждение выполнения Fresh '
                            . '(поставьте чекбокс "Подтверждаю операции CREATE/DROP").',
                    ];
                }
                // Drop + verify
                DBA::dropDatabase($cfg);
                $afterDropExists = DBA::databaseExists($cfg);
                if ($afterDropExists) {
                    return [
                        'ok' => false,
                        'title' => 'Fresh',
                        'message' => 'Не удалось удалить БД (проверьте привилегии).',
                        'details' => DBA::testReport($cfg),
                    ];
                }

                // Create + verify
                DBA::createDatabaseIfNotExists($cfg);
                $afterCreateExists = DBA::databaseExists($cfg);
                if (!$afterCreateExists) {
                    return [
                        'ok' => false,
                        'title' => 'Fresh',
                        'message' => 'Не удалось создать БД (проверьте привилегии).',
                        'details' => DBA::testReport($cfg),
                    ];
                }

                $mig  = $runner['migrate'] ? ($runner['migrate'])() : ['errors' => ['no runner']];
                $seed = $runner['seed']    ? ($runner['seed'])()    : ['errors' => ['no runner']];

                $ok = empty($mig['errors'] ?? []) && empty($seed['errors'] ?? []);
                return [
                    'ok' => (bool)$ok,
                    'title' => 'Fresh',
                    'message' => $ok
                        ? 'Выполнено: drop + migrate + seed.'
                        : 'Fresh завершился с ошибками.',
                    'details' => [
                        'migrate' => $mig,
                        'seed' => $seed,
                        'report' => DBA::testReport($cfg),
                    ],
                ];
            }

            case 'lock_create': {
                $res = install_lock_create($root);
                return [
                    'ok' => $res['ok'],
                    'title' => 'installed.lock',
                    'message' => $res['message'],
                ];
            }
            case 'lock_delete': {
                if (!$lockConf) {
                    return [
                        'ok' => false,
                        'title' => 'installed.lock',
                        'message' => 'Требуется подтверждение удаления lock.',
                    ];
                }
                $res = install_lock_delete($root);
                return [
                    'ok' => $res['ok'],
                    'title' => 'installed.lock',
                    'message' => $res['message'],
                ];
            }

            default:
                return [
                    'ok' => false,
                    'title' => 'Неизвестная команда',
                    'message' => 'Действие не поддерживается.',
                ];
        }
    } catch (\Throwable $e) {
        return [
            'ok' => false,
            'title' => 'Ошибка выполнения',
            'message' => $e->getMessage(),
        ];
    }
}

/**
 * Render action result with pretty details.
 *
 * @param array{ok:bool,title:string,message:string,details?:mixed} $result
 * @return void
 */
function install_render_result(array $result): void
{
    $type = $result['ok'] ? 'info' : 'error';
    echo '<div class="panel"><div class="hd"><strong>'
        . htmlspecialchars($result['title']) . '</strong></div><div class="bd">';
    admin_alert($type, $result['message']);

    if (array_key_exists('details', $result)) {
        echo '<pre style="white-space:pre-wrap;overflow:auto;padding:10px;'
           . 'background:#f8f8f8;border:1px dashed #ccc;border-radius:6px">';
        if (is_array($result['details']) || is_object($result['details'])) {
            echo htmlspecialchars(print_r($result['details'], true));
        } else {
            echo htmlspecialchars((string)$result['details']);
        }
        echo '</pre>';
    }
    echo '</div></div>';

    if ($result['ok'] && in_array($result['title'], ['Миграции', 'Сиды', 'Fresh'], true)) {
        echo '<div class="panel"><div class="hd"><strong>Post-install</strong></div><div class="bd">';
        echo '<p>При необходимости создайте <code>installed.lock</code> в корне публичной части, '
           . 'чтобы защитить инсталлятор от повторного запуска.</p>';
        echo '</div></div>';
    }
}

// -------- Controller-like glue for the module --------

$root = realpath(__DIR__ . '/../../') ?: __DIR__ . '/../../';
$cfg = install_db_config();
$runner = install_try_load_runner($root);

$action     = (string)($_POST['action'] ?? '');
$confirm    = isset($_POST['confirm']) && $_POST['confirm'] === '1';
$lockExists = install_lock_exists($root);
$lockConf   = isset($_POST['lock_confirm']) && $_POST['lock_confirm'] === '1';

install_render_form($cfg, (bool)($runner['migrate'] && $runner['seed']), $lockExists);

if ($action !== '') {
    $res = install_handle_action($action, $cfg, $runner, $confirm, $root, $lockConf);
    install_render_result($res);
}
