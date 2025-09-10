<?php

use Faravel\Support\Facades\DB;

return new class {
    public function up()
    {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(190) NOT NULL PRIMARY KEY,
    `value` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        DB::connection()->exec($sql);
    }

    public function down()
    {
        DB::connection()->exec('DROP TABLE IF EXISTS settings');
    }
};
