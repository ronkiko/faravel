<?php // v0.4.2
/* tools/migrate.php
Purpose: CLI-обёртка для framework/migrator.php. Умеет запускать migrate/seed/fresh из командной
         строки. Теперь поддерживает --json с машинным выводом и «тихим» перехватом stdout.
FIX: Добавлен флаг --json; JSON-структуры для режимов migrate/seed/fresh; коды возврата сохранены.
*/

declare(strict_types=1);

// Lightweight project root detection (tools/.. => project root)
$root = realpath(__DIR__ . '/..') ?: dirname(__DIR__);

// Ensure migrator is available
$runner = $root . '/framework/migrator.php';
if (!is_file($runner)) {
    fwrite(STDERR, "[ERR] framework/migrator.php not found at: {$runner}\n");
    exit(1);
}

/** @psalm-suppress UnresolvableInclude */
require_once $runner;

/**
 * Print usage help.
 *
 * @return void
 */
function cli_print_help(): void
{
    $help = <<<TXT
Faravel Migrator CLI

Usage:
  php tools/migrate.php --migrate [--json]
  php tools/migrate.php --seed [--json]
  php tools/migrate.php --fresh [--json]
  php tools/migrate.php --help

Options:
  --migrate   Run all migrations (database/migrations)
  --seed      Run seeders (database/seeders)
  --fresh     Run migrate + seed (DB drop/create выполняйте командами БД или админкой)
  --json      Print machine-readable JSON result
  --help      Show this help

Exit codes:
  0 on success, 1 on error
TXT;
    fwrite(STDOUT, $help . "\n");
}

/**
 * Parse CLI arguments and return selected mode + flags.
 *
 * @param array<int,string> $argv
 * @return array{mode:'migrate'|'seed'|'fresh'|'help'|null,json:bool}
 */
function cli_parse_args(array $argv): array
{
    $mode = null;
    $json = false;
    foreach ($argv as $arg) {
        if ($arg === '--json') {
            $json = true;
            continue;
        }
        if ($arg === '--migrate') $mode = 'migrate';
        if ($arg === '--seed')    $mode = 'seed';
        if ($arg === '--fresh')   $mode = 'fresh';
        if ($arg === '--help')    $mode = 'help';
    }
    return ['mode' => $mode, 'json' => $json];
}

/**
 * Return JSON safely with UTF-8.
 *
 * @param mixed $data
 * @return void
 */
function cli_print_json($data): void
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    fwrite(STDOUT, $json . "\n");
}

/**
 * Run selected mode via migrator functions.
 *
 * @param 'migrate'|'seed'|'fresh' $mode
 * @param bool                     $asJson
 * @return int Exit code
 */
function cli_run_mode(string $mode, bool $asJson): int
{
    // Silence stdout of migrator if we need clean JSON.
    $buffering = $asJson;
    if ($buffering) ob_start();

    if ($mode === 'migrate') {
        $res = faravel_migrate_all();
        $ok = empty($res['errors'] ?? []);
        $stdout = $buffering ? (string)ob_get_clean() : '';
        if ($asJson) {
            cli_print_json([
                'mode'    => 'migrate',
                'ok'      => $ok,
                'applied' => $res['applied'] ?? [],
                'errors'  => $res['errors'] ?? [],
                'stdout'  => $stdout,
            ]);
        } elseif (!$ok) {
            fwrite(STDERR, "[migrate] errors:\n" . print_r($res['errors'], true) . "\n");
        }
        return $ok ? 0 : 1;
    }

    if ($mode === 'seed') {
        $res = faravel_seed_all();
        $ok = empty($res['errors'] ?? []);
        $stdout = $buffering ? (string)ob_get_clean() : '';
        if ($asJson) {
            cli_print_json([
                'mode'   => 'seed',
                'ok'     => $ok,
                'seeded' => $res['seeded'] ?? [],
                'errors' => $res['errors'] ?? [],
                'stdout' => $stdout,
            ]);
        } elseif (!$ok) {
            fwrite(STDERR, "[seed] errors:\n" . print_r($res['errors'], true) . "\n");
        }
        return $ok ? 0 : 1;
    }

    if ($mode === 'fresh') {
        // Fresh = migrate + seed (drop/create делайте в админке или отдельными командами).
        $m1 = faravel_migrate_all();
        $s1 = faravel_seed_all();
        $ok = empty($m1['errors'] ?? []) && empty($s1['errors'] ?? []);
        $stdout = $buffering ? (string)ob_get_clean() : '';
        if ($asJson) {
            cli_print_json([
                'mode'    => 'fresh',
                'ok'      => $ok,
                'migrate' => $m1,
                'seed'    => $s1,
                'stdout'  => $stdout,
            ]);
        } elseif (!$ok) {
            fwrite(STDERR, "[fresh] migrate errors:\n" . print_r($m1['errors'] ?? [], true) . "\n");
            fwrite(STDERR, "[fresh] seed errors:\n" . print_r($s1['errors'] ?? [], true) . "\n");
        }
        return $ok ? 0 : 1;
    }

    if ($buffering) ob_end_clean();
    fwrite(STDERR, "[ERR] Unsupported mode\n");
    return 1;
}

// ---- main ----
$args = cli_parse_args(array_slice($argv, 1));
if ($args['mode'] === null || $args['mode'] === 'help') {
    cli_print_help();
    exit($args['mode'] === 'help' ? 0 : 1);
}

exit(cli_run_mode($args['mode'], $args['json']));
