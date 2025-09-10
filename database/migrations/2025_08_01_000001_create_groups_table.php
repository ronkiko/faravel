<?php

use Faravel\Support\Facades\DB;

return new class {
    public function up()
    {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS groups (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    reputation INT UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        DB::connection()->exec($sql);
    }

    public function down()
    {
        DB::connection()->exec('DROP TABLE IF EXISTS groups');
    }
};
