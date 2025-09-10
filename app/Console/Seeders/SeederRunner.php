<?php // v0.4.2
/* app/Console/Seeders/SeederRunner.php
Назначение: запуск сидеров Faravel (инициализация данных).
*/

namespace App\Console\Seeders;

use Faravel\Database\Database;

class SeederRunner
{
    protected Database $db;
    protected string $seedersPath;

    public function __construct(Database $db, string $seedersPath)
    {
        $this->db = $db;
        $this->seedersPath = rtrim($seedersPath, '/');
    }

    /**
     * Запуск всех сидеров в папке.
     */
    public function seed(): void
    {
        $files = glob($this->seedersPath . '/*.php');
        sort($files);

        foreach ($files as $file) {
            $class = $this->classFromFile($file);
            $instance = $this->loadSeederInstance($file, $class);

            if (method_exists($instance, 'run')) {
                $instance->run();
                echo "[seed] executed: {$class}\n";
            } else {
                throw new \RuntimeException("Seeder '{$class}' has no run() method");
            }
        }
    }

    /**
     * Алиас для совместимости с MigrationRunner
     */
    public function run(): void
    {
        $this->seed();
    }

    protected function classFromFile(string $file): string
    {
        $base = basename($file, '.php');
        $parts = preg_split('/[^a-zA-Z0-9]+/', $base, -1, PREG_SPLIT_NO_EMPTY);
        $class = '';
        foreach ($parts as $p) { $class .= ucfirst($p); }
        return $class ?: $base;
    }

    protected function loadSeederInstance(string $file, string $expectedClass)
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
                if ($ref->hasMethod('run')) { $class = $c; break; }
            }
        }

        if (!$class) {
            throw new \RuntimeException("Seeder class not found in file: {$file}");
        }

        // Передаём db в конструктор, если требуется
        $ref = new \ReflectionClass($class);
        $ctor = $ref->getConstructor();
        if ($ctor && $ctor->getNumberOfParameters() > 0) {
            return $ref->newInstanceArgs([$this->db]);
        }

        return new $class();
    }
}
