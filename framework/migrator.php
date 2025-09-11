<?php // v0.4.1
/* framework/migrator.php
Purpose: Единый раннер миграций и сидов для SafeMode-админки и CLI. Выполняет лёгкий
         бутстрап (контейнер + фасады), затем прогоняет database/migrations и seeders.
FIX: Начальная реализация: функции faravel_migrate_all() и faravel_seed_all(), без
     загрузки маршрутов/boot. Поддержка анонимных классов миграций (return new class {up}).
*/

declare(strict_types=1);

use Faravel\Foundation\Application;
use Faravel\Support\Facades\Facade;
use Faravel\Database\Database;

/**
 * Выполняет лёгкий бутстрап контейнера и фасадов для задач БД.
 *
 * Зачем: миграции/сидеры используют Facade\DB и Faravel\Database\Database.
 * Нам нужен контейнер и провайдеры конфигов/DB, но не нужны маршруты и boot-хуки.
 *
 * @param string|null $projectRoot Абсолютный корень проекта. Если null — вычислим автоматически.
 *
 * Preconditions:
 * - В $projectRoot существуют каталоги framework/, config/, database/.
 *
 * Side effects:
 * - Создаёт Application, фиксирует его как текущий инстанс; загружает .env и конфиги,
 *   регистрирует провайдеры (без loadRoutes/boot).
 *
 * @return Application
 * @throws RuntimeException если не найден корень проекта или init не удался.
 * @example $app = faravel_migrator_boot(__DIR__ . '/..');
 */
function faravel_migrator_boot(?string $projectRoot = null): Application
{
    static $bootedApp = null;
    if ($bootedApp instanceof Application) {
        return $bootedApp;
    }

    // 1) Корень проекта
    $root = $projectRoot ?: dirname(__DIR__); // framework/.. => проект
    if (!is_dir($root)) {
        throw new RuntimeException('Invalid project root for migrator: ' . (string)$root);
    }

    // 2) Базовый init (только автозагрузка ядра и хелперы)
    require_once $root . '/framework/init.php';
    require_once $root . '/framework/helpers.php';

    // 3) Контейнер приложения и привязка фасадов
    $app = new Application($root);
    if (method_exists(Application::class, 'setInstance')) {
        Application::setInstance($app);
    }
    Facade::setApplication($app);

    // 4) Регистрируем провайдеры (env/config/db/и т.д.). Маршруты/boot НЕ трогаем.
    $app->registerConfiguredProviders();

    $bootedApp = $app;
    return $app;
}

/**
 * Прогоняет ВСЕ миграции из database/migrations (в лексикографическом порядке).
 *
 * Формат миграции: файл возвращает объект (анон. класс) с методом up():
 *     <?php
 *     use Faravel\Support\Facades\DB;
 *     return new class { public function up(){ DB::connection()->exec('...'); } };
 *
 * @return array{applied:array<int,string>, errors:array<int,string>}
 * @example $res = faravel_migrate_all(); // ['applied'=>['2025_...php',...], 'errors'=>[]]
 */
function faravel_migrate_all(): array
{
    $app = faravel_migrator_boot(); // контейнер + DB фасад доступны

    $root = $app->basePath() ?? dirname(__DIR__);
    $dir = $root . '/database/migrations';
    $result = ['applied' => [], 'errors' => []];

    if (!is_dir($dir)) {
        $result['errors'][] = 'Migrations dir not found: ' . $dir;
        return $result;
    }

    $files = glob($dir . '/*.php') ?: [];
    sort($files, SORT_STRING);

    foreach ($files as $file) {
        $name = basename($file);
        try {
            /** @var object|null $migration */
            $migration = require $file;

            if (is_object($migration) && method_exists($migration, 'up')) {
                $migration->up();
                $result['applied'][] = $name;
                echo "[migrate] applied: {$name}\n";
            } else {
                $result['errors'][] = "[migrate] {$name}: invalid format (no object with up())";
            }
        } catch (Throwable $e) {
            $result['errors'][] = "[migrate] {$name}: " . $e->getMessage();
        }
    }

    return $result;
}

/**
 * Прогоняет сидеры из database/seeders.
 *
 * Контракты (best-effort):
 *  - Database\Seeders\Seeder (база): __construct(Database $db), method run(): void
 *  - Database\Seeders\DatabaseSeeder: orchestrator общего набора
 *  - Отдельные сидеры (AbilitiesSeeder/PerksSeeder): могут использовать Facade\DB
 *    или расширять базовый Seeder.
 *
 * @return array{seeded:array<int,string>, errors:array<int,string>}
 * @example $res = faravel_seed_all(); // ['seeded'=>['DatabaseSeeder',...], 'errors'=>[]]
 */
function faravel_seed_all(): array
{
    $app = faravel_migrator_boot();

    // Убедимся, что есть подключение DB в контейнере
    /** @var Database $db */
    $db = \app('db');

    $root = $app->basePath() ?? dirname(__DIR__);
    $dir  = $root . '/database/seeders';
    $result = ['seeded' => [], 'errors' => []];

    if (!is_dir($dir)) {
        $result['errors'][] = 'Seeders dir not found: ' . $dir;
        return $result;
    }

    // Подгружаем базовый Seeder первым, чтобы наследование не падало
    $base = $dir . '/Seeder.php';
    if (is_file($base)) {
        /** @psalm-suppress UnresolvableInclude */
        require_once $base;
    }

    // Загружаем остальные классы сидеров
    foreach (glob($dir . '/*.php') ?: [] as $file) {
        if ($file === $base) continue;
        /** @psalm-suppress UnresolvableInclude */
        require_once $file;
    }

    $queue = [
        'Database\\Seeders\\DatabaseSeeder',
        'Database\\Seeders\\AbilitiesSeeder',
        'Database\\Seeders\\PerksSeeder',
    ];

    foreach ($queue as $class) {
        if (!class_exists($class)) {
            continue;
        }
        try {
            $ref = new ReflectionClass($class);

            // Если сидер — наследник базы, инжектим Database $db в конструктор
            if ($ref->isSubclassOf('Database\\Seeders\\Seeder')) {
                /** @var object $seeder */
                $seeder = $ref->newInstance($db);
            } else {
                /** @var object $seeder */
                $seeder = $ref->newInstance();
            }

            if (!method_exists($seeder, 'run')) {
                $result['errors'][] = "[seed] {$class}: no run() method";
                continue;
            }

            $seeder->run();
            $result['seeded'][] = $class;
            echo "[seed] done: {$class}\n";
        } catch (Throwable $e) {
            $result['errors'][] = "[seed] {$class}: " . $e->getMessage();
        }
    }

    return $result;
}
