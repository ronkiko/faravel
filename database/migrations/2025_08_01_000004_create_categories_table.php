<?php

use Faravel\Support\Facades\DB;

return new class {
    public function up()
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS categories (
  id CHAR(36) NOT NULL,
  slug VARCHAR(100) NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT DEFAULT NULL,
  order_id TINYINT(3) UNSIGNED DEFAULT NULL,
  is_visible TINYINT(1) DEFAULT 0,
  min_group TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY slug (slug),
  CONSTRAINT fk_categories_min_group FOREIGN KEY (min_group) REFERENCES groups(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        DB::connection()->exec($sql);
    }

    public function down()
    {
        DB::connection()->exec('DROP TABLE IF EXISTS categories');
    }
};