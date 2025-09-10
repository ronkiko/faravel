<?php // v0.4.1
/* app/Console/Migrations/MigrationRunner.php
Назначение: управление миграциями Faravel (запуск, откат, статус).
FIX: добавлен статический метод run() для удобного вызова в установщике.
*/

namespace App\Console\Migrations;

use Faravel\Database\Database;

class MigrationRunner
{
    protected Database $db;
    protected string $migrationsPath;

    public function __construct(Database $db, string $migrationsPath)
    {
        $this->db = $db;
        $this->migrationsPath = rtrim($migrationsPath, '/');
        $this->ensureMigrationsTable();
    }

    /**
     * Универсальный статический запуск.
     *
     * @param Database $db Экземпляр базы данных.
     * @param string $path Путь к папке миграций.
     * @param string $command migrate|rollback|status
     * @param array<int,mixed> $args Доп. аргументы (например шаги для rollback).
     * @return void
     */
    public static function run(Database $db, string $path, string $command, array $args = []): void
    {
        $runner = new self($db, $path);

        switch ($command) {
            case 'migrate':
                $runner->migrate();
                break;
            case 'rollback':
                $steps = $args[0] ?? 1;
                $runner->rollback((int)$steps);
                break;
            case 'status':
                $runner->status();
                break;
            default:
                throw new \InvalidArgumentException("Unknown command: {$command}");
        }
    }

    protected function ensureMigrationsTable(): void
    {
        $this->db->statement("CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            batch INT NOT NULL,
            ran_at INT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    protected function getRan(): array
    {
        $rows = $this->db->select("SELECT name FROM migrations ORDER BY id ASC");
        return array_map(fn($r) => $r['name'], $rows);
    }

    /** Показать Applied/Pending и вернуть массивы */
    public function status(): array
    {
        $files = glob($this->migrationsPath . '/*.php');
        sort($files);
        $applied = $this->getRan();
        $pending = [];

        foreach ($files as $f) {
            $name = basename($f);
            if (!in_array($name, $applied, true)) {
                $pending[] = $name;
            }
        }

        echo "Applied:\n";
        foreach ($applied as $a) { echo "  - {$a}\n"; }
        echo "Pending:\n";
        foreach ($pending as $p) { echo "  - {$p}\n"; }

        return ['applied' => $applied, 'pending' => $pending];
    }

    public function migrate(): void
    {
        $files = glob($this->migrationsPath . '/*.php');
        sort($files);
        $ran = $this->getRan();
        $batch = (int)$this->db->scalar("SELECT IFNULL(MAX(batch),0)+1 FROM migrations");

        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $ran, true)) { continue; }

            $class = $this->classFromFile($file);
            $instance = $this->loadMigrationInstance($file, $class);

            if (method_exists($instance, 'up')) {
                $instance->up();
                $this->db->insert(
                    "INSERT INTO migrations (name, batch, ran_at) VALUES (?,?,?)",
                    [$name, $batch, time()]
                );
                echo "[migrate] applied: {$name}\n";
            } else {
                throw new \RuntimeException("Migration '{$name}' has no up() method");
            }
        }
    }

    public function rollback(int $steps = 1): void
    {
        $maxBatch = (int)$this->db->scalar("SELECT IFNULL(MAX(batch),0) FROM migrations");
        if ($maxBatch === 0) {
            echo "[rollback] nothing to rollback\n";
            return;
        }

        $target = max(0, $maxBatch - $steps + 1);
        for ($b = $maxBatch; $b >= $target; $b--) {
            $rows = $this->db->select(
                "SELECT name FROM migrations WHERE batch = ? ORDER BY id DESC",
                [$b]
            );

            foreach ($rows as $row) {
                $name = $row['name'];
                $file = $this->migrationsPath . '/' . $name;

                $instance = $this->loadMigrationInstance($file, $this->classFromFile($file));
                if (method_exists($instance, 'down')) {
                    $instance->down();
                    echo "[rollback] reverted: {$name}\n";
                }

                $this->db->statement("DELETE FROM migrations WHERE name = ?", [$name]);
            }
        }
    }

    /** 2025_08_01_000001_create_groups_table.php -> CreateGroupsTable */
    protected function classFromFile(string $file): string
    {
        $base = basename($file, '.php');
        $parts = preg_split('/[^a-zA-Z0-9]+/', $base, -1, PREG_SPLIT_NO_EMPTY);
        while (!empty($parts) && ctype_digit($parts[0])) { array_shift($parts); }
        $class = '';
        foreach ($parts as $p) { $class .= ucfirst($p); }
        return $class ?: $base;
    }

    protected function loadMigrationInstance(string $file, string $expectedClass)
    {
        $before = get_declared_classes();
        require_once $file;
        $after  = get_declared_classes();
        $new    = array_values(array_diff($after, $before));

        $class = class_exists($expectedClass) ? $expectedClass : null;

        if (!$class) {
            foreach ($new as $c) {
                $ref = new \ReflectionClass($c);
                if (realpath($ref->getFileName()) !== realpath($file)) { continue; }
                if ($ref->hasMethod('up')) { $class = $c; break; }
            }
        }

        if (!$class) {
            throw new \RuntimeException("Migration class not found in file: {$file}");
        }

        $ref = new \ReflectionClass($class);
        $ctor = $ref->getConstructor();
        if ($ctor && $ctor->getNumberOfParameters() > 0) {
            return $ref->newInstanceArgs([$this->db]);
        }
        return $ref->newInstance();
    }
}
