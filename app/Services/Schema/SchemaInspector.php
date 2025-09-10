<?php // v0.3.101 — SchemaInspector: централизованные проверки таблиц/колонок с кэшем на запрос.

namespace App\Services\Schema;

use Faravel\Support\Facades\DB;

class SchemaInspector
{
    /** @var array<string,bool> */
    private array $tableCache = [];
    /** @var array<string,bool> key = "table:column" */
    private array $columnCache = [];

    public function hasTable(string $name): bool
    {
        if (isset($this->tableCache[$name])) return $this->tableCache[$name];

        try {
            $cnt = (int) DB::scalar(
                "SELECT COUNT(*) FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
                [$name]
            );
            return $this->tableCache[$name] = ($cnt > 0);
        } catch (\Throwable $e) {
            try { DB::select("SELECT 1 FROM `{$name}` LIMIT 1"); return $this->tableCache[$name] = true; }
            catch (\Throwable $e2) { return $this->tableCache[$name] = false; }
        }
    }

    public function hasColumn(string $table, string $column): bool
    {
        $key = $table.':'.$column;
        if (isset($this->columnCache[$key])) return $this->columnCache[$key];

        try {
            $cnt = (int) DB::scalar(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
                [$table, $column]
            );
            return $this->columnCache[$key] = ($cnt > 0);
        } catch (\Throwable $e) {
            return $this->columnCache[$key] = false;
        }
    }
}
