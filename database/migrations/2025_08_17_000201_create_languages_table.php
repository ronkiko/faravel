<?php // v0.3.2

use Faravel\Support\Facades\DB;

return new class {
    public function up()
    {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS languages (
    id TINYINT UNSIGNED NOT NULL,
    code VARCHAR(8) NOT NULL,
    name VARCHAR(64) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_languages_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        DB::table('languages'); // инициализация фасада
        DB::connection()->exec($sql);

        // Начальные значения (EN=1, RU=2)
        DB::connection()->exec("INSERT INTO languages (id, code, name, is_active, sort) VALUES 
            (1, 'en', 'English', 1, 10),
            (2, 'ru', 'Русский', 1, 20)
            ON DUPLICATE KEY UPDATE code=VALUES(code), name=VALUES(name), is_active=VALUES(is_active), sort=VALUES(sort)");
    }

    public function down()
    {
        DB::connection()->exec('DROP TABLE IF EXISTS languages');
    }
};
