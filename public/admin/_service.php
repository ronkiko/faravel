<?php // v0.4.4
/* public/admin/_service.php
Purpose: Модуль «Проверки БД» для SafeMode-админки. Диагностика и операции (ping/exists/create/
         drop/canConnect/testReport) через DatabaseAdmin внутри общей оболочки админки.
FIX: Переведено на корректные статические методы DatabaseAdmin::pingServer|databaseExists|
     createDatabaseIfNotExists|dropDatabase|canConnect|testReport. Убраны несуществующие
     методы ping/exists/create/drop и объектные вызовы.
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
 * Read DB config from POST with ENV fallbacks.
 *
 * @return array{
 *   driver:string, host:string, port:string, database:string, username:string, password:string
 * }
 */
function service_db_config(): array
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
 * Execute selected action against DatabaseAdmin, handling exceptions.
 *
 * @param string $action One of: ping|exists|create|drop|connect|report
 * @param array{
 *   driver:string, host:string, port:string, database:string, username:string, password:string
 * } $cfg
 * @param bool $confirmed Required for destructive ops (create/drop).
 *
 * Preconditions:
 * - $action must be whitelisted; $cfg fields should be valid; CREATE/DROP require $confirmed.
 *
 * Side effects:
 * - May create or drop database when requested and confirmed.
 *
 * @return array{ok:bool,title:string,message:string,details?:mixed}
 */
function service_handle_action(string $action, array $cfg, bool $confirmed = false): array
{
    try {
        switch ($action) {
            case 'ping': {
                $elapsed = DBA::pingServer($cfg); // seconds (float)
                return [
                    'ok' => true,
                    'title' => 'Ping',
                    'message' => 'Сервер БД отвечает (~' . number_format($elapsed * 1000, 0)
                        . ' ms).',
                ];
            }
            case 'exists': {
                $ex = DBA::databaseExists($cfg);
                return [
                    'ok' => (bool)$ex,
                    'title' => 'Проверка существования БД',
                    'message' => $ex ? 'База существует.' : 'База не найдена.',
                ];
            }
            case 'create': {
                if (!$confirmed) {
                    return [
                        'ok' => false,
                        'title' => 'Создание БД',
                        'message' => 'Требуется подтверждение операции.',
                    ];
                }
                DBA::createDatabaseIfNotExists($cfg);
                return [
                    'ok' => true,
                    'title' => 'Создание БД',
                    'message' => 'База создана (или уже существовала).',
                ];
            }
            case 'drop': {
                if (!$confirmed) {
                    return [
                        'ok' => false,
                        'title' => 'Удаление БД',
                        'message' => 'Требуется подтверждение операции.',
                    ];
                }
                DBA::dropDatabase($cfg);
                return [
                    'ok' => true,
                    'title' => 'Удаление БД',
                    'message' => 'База удалена (если существовала).',
                ];
            }
            case 'connect': {
                $ok = DBA::canConnect($cfg);
                return [
                    'ok' => (bool)$ok,
                    'title' => 'Проверка подключения',
                    'message' => $ok ? 'Подключение успешно.' : 'Подключиться не удалось.',
                ];
            }
            case 'report': {
                $report = DBA::testReport($cfg);
                return [
                    'ok' => (bool)$report['server_ok'],
                    'title' => 'Диагностический отчёт',
                    'message' => $report['server_ok']
                        ? 'Сформирован отчёт диагностики.'
                        : 'Сервер недоступен: ' . (string)($report['error'] ?? 'unknown'),
                    'details' => $report,
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
 * Render DB config form and action buttons (view-only).
 *
 * @param array{
 *   driver:string, host:string, port:string, database:string, username:string, password:string
 * } $cfg
 * @return void
 */
function service_render_form(array $cfg): void
{
    echo '<div class="panel"><div class="hd"><strong>Настройки БД</strong></div><div class="bd">';
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
    echo '<button name="action" value="ping">Ping</button>';
    echo '<button name="action" value="exists">Проверить наличие БД</button>';
    echo '<button name="action" value="connect">Проверить подключение</button>';
    echo '<button name="action" value="report">Диагностический отчёт</button>';
    echo '<label style="margin-left:16px">'
        . '<input type="checkbox" name="confirm" value="1"> Подтверждаю операции CREATE/DROP'
        . '</label>';
    echo '<button name="action" value="create">Создать БД</button>';
    echo '<button name="action" value="drop">Удалить БД</button>';
    echo '</div>';

    echo '</form></div></div>';
}

/**
 * Render action result as alert and optional preformatted block.
 *
 * @param array{ok:bool,title:string,message:string,details?:mixed} $result
 * @return void
 */
function service_render_result(array $result): void
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
}

// ---------- Controller-like glue ----------

$config    = service_db_config();
$action    = (string)($_POST['action'] ?? '');
$confirmed = isset($_POST['confirm']) && $_POST['confirm'] === '1';

service_render_form($config);

if ($action !== '') {
    $res = service_handle_action($action, $config, $confirmed);
    service_render_result($res);
}
