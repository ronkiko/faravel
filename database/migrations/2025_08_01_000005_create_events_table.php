<?php

use Faravel\Support\Facades\DB;

return new class {
    public function up()
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS events (
  hash CHAR(32) NOT NULL,
  type VARCHAR(32) NOT NULL,
  data JSON NOT NULL,
  node VARCHAR(32) NOT NULL,
  created INT(10) UNSIGNED NOT NULL,
  PRIMARY KEY (hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        DB::connection()->exec($sql);
    }

    public function down()
    {
        DB::connection()->exec('DROP TABLE IF EXISTS events');
    }
};