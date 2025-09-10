<?php

use Faravel\Support\Facades\DB;

return new class {
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `category_forum` (
  `category_id` CHAR(36) NOT NULL,
  `forum_id`    CHAR(36) NOT NULL,
  `position`    INT UNSIGNED NULL,
  PRIMARY KEY (`category_id`, `forum_id`),
  KEY `cf_forum_idx` (`forum_id`, `category_id`),
  CONSTRAINT `cf_category_fk`
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `cf_forum_fk`
    FOREIGN KEY (`forum_id`) REFERENCES `forums`(`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function down(): void
    {
        try { DB::statement("ALTER TABLE `category_forum` DROP FOREIGN KEY `cf_category_fk`"); } catch (\Throwable $e) {}
        try { DB::statement("ALTER TABLE `category_forum` DROP FOREIGN KEY `cf_forum_fk`"); } catch (\Throwable $e) {}
        DB::statement("DROP TABLE IF EXISTS `category_forum`");
    }
};
