<?php

use Faravel\Support\Facades\DB;

return new class {
    public function up()
    {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS roles (
  id TINYINT NOT NULL,
  name VARCHAR(50) NOT NULL,
  label VARCHAR(100) NOT NULL,
  description TEXT,
  PRIMARY KEY (id),
  UNIQUE KEY name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        DB::connection()->exec($sql);
    }

    public function down()
    {
        DB::connection()->exec('DROP TABLE IF EXISTS roles');
    }
};
