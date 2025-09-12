<?php // v0.4.2
/* framework/migrator.php
Purpose: Единый раннер миграций и сидов для SafeMode-админки и CLI. Выполняет лёгкий
         бутстрап (контейнер + фасады), затем прогоняет database/migrations и seeders.
FIX: Подключён автозагрузчик приложения (app/init.php), чтобы классы App\Providers\*
     и Database\Seeders\* были видны. Добавлен префлайт на биндинг 'db' и fallback-require
     сидеров на случай кастомной структуры.
*/

declare(strict_types=1);

use Faravel\Foundation\Application;
use Faravel\Support\Facades\Facade;

/**
 * Boot a minimal application context for DB tasks.
 *
 * @param string|null $projectRoot Absolute project root; default auto-detects from framework/.
 *
 * Preconditions:
 * - $projectRoot contains framework/, app/, config/, database/.
 * Side effects:
 * - Creates Application, sets Facades app, registers providers (no routes/boot).
 *
 * @return Application
 * @throws RuntimeException When project root invalid or 'db' binding is missing.
 * @example $app = faravel_migrator_boot(__DIR__ . '/..');
 */
function faravel_migrator_boot(?string $projectRoot = null): Application
{
    static $bootedApp = null;
    if ($bootedApp instanceof Application) {
        return $bootedApp;
    }

    // 1) Locate root
    $root = $projectRoot ?: dirname(__DIR__);
    if (!is_dir($root)) {
        throw new RuntimeException('Invalid project root for migrator: ' . (string)$root);
    }

    // 2) Core + App autoloaders + helpers
    require_once $root . '/framework/init.php';
    if (is_file($root . '/app/init.php')) {
        require_once $root . '/app/init.php';
    }
    require_once $root . '/framework/helpers.php';

    // 3) Container and Facades
    $app = new Application($root);
    if (method_exists(Application::class, 'setInstance')) {
        Application::setInstance($app);
    }
    Facade::setApplication($app);

    // 4) Providers (env/config/db/…); do not load routes/boot
    $app->registerConfiguredProviders();

    // 4.1) Preflight: ensure 'db' binding exists (DatabaseServiceProvider loaded)
    try {
        $app->make('db');
    } catch (\Throwable $e) {
        throw new RuntimeException(
            "Container binding [db] not found. Добавьте App\\Providers\\DatabaseServiceProvider ".
            "в 'providers' config/app.php до запуска миграций."
        );
    }

    $bootedApp = $app;
    return $app;
}

/**
 * Run ALL migrations from database/migrations (lexicographic order).
 *
 * File contract: returns an object (anonymous class) with up():
 *   <?php
 *   use Faravel\Support\Facades\DB;
 *   return new class { public function up(): void { DB::statement('…'); } };
 *
 * @return array{applied:array<int,string>, errors:array<int,string>}
 * @example $res = faravel_migrate_all();
 */
function faravel_migrate_all(): array
{
    $app = faravel_migrator_boot();

    $root = $app->basePath() ?? dirname(__DIR__);
    $dir  = $root . '/database/migrations';
    $result = ['applied' => [], 'errors' => []];

    if (!is_dir($dir)) {
        $result['errors'][] = 'Migrations dir not found: ' . $dir;
        return $result;
    }

    $files = glob($dir . '/*.php') ?: [];
    sort($files, SORT_STRING); // deterministic order

    foreach ($files as $file) {
        $name = basename($file);
        try {
            /** @var object{up:callable}|mixed $migration */
            /** @psalm-suppress UnresolvableInclude */
            $migration = require $file;

            if (is_object($migration) && method_exists($migration, 'up')) {
                $migration->up();
                $result['applied'][] = $name;
                echo "[migrate] applied: {$name}\n";
            } else {
                $result['errors'][] = "[migrate] {$name}: invalid format (no object with up())";
            }
        } catch (\Throwable $e) {
            $result['errors'][] = "[migrate] {$name}: " . $e->getMessage();
        }
    }

    return $result;
}

/**
 * Run seeders from database/seeders.
 *
 * Contracts (best-effort):
 *  - Database\Seeders\DatabaseSeeder::run()
 *  - Database\Seeders\AbilitiesSeeder::run()
 *  - Database\Seeders\PerksSeeder::run()
 *
 * @return array{seeded:array<int,string>, errors:array<int,string>}
 * @example $res = faravel_seed_all();
 */
function faravel_seed_all(): array
{
    $app = faravel_migrator_boot();

    $root = $app->basePath() ?? dirname(__DIR__);
    $dir  = $root . '/database/seeders';
    $result = ['seeded' => [], 'errors' => []];

    if (!is_dir($dir)) {
        $result['errors'][] = 'Seeders dir not found: ' . $dir;
        return $result;
    }

    // Fallback include: ensure classes are declared even if autoload is customized
    $base = $dir . '/Seeder.php';
    if (is_file($base)) {
        /** @psalm-suppress UnresolvableInclude */
        require_once $base;
    }
    foreach (glob($dir . '/*.php') ?: [] as $file) {
        if ($file === $base) continue;
        /** @psalm-suppress UnresolvableInclude */
        require_once $file;
    }

    $classes = [
        'Database\\Seeders\\DatabaseSeeder',
        'Database\\Seeders\\AbilitiesSeeder',
        'Database\\Seeders\\PerksSeeder',
    ];

    foreach ($classes as $class) {
        try {
            if (!class_exists($class)) {
                $result['errors'][] = "[seed] {$class}: class not found";
                continue;
            }

            $ref  = new ReflectionClass($class);
            $ctor = $ref->getConstructor();
            $seeder = ($ctor && $ctor->getNumberOfParameters() > 0)
                ? $ref->newInstance($app->make('db'))
                : $ref->newInstance();

            if (!method_exists($seeder, 'run')) {
                $result['errors'][] = "[seed] {$class}: no run() method";
                continue;
            }

            $seeder->run();
            $result['seeded'][] = $class;
            echo "[seed] done: {$class}\n";
        } catch (\Throwable $e) {
            $result['errors'][] = "[seed] {$class}: " . $e->getMessage();
        }
    }

    return $result;
}
