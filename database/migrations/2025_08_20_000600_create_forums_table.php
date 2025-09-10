<?php

use Faravel\Support\Facades\DB;

return new class {
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE IF NOT EXISTS `forums` (
  `id`              CHAR(36)     NOT NULL,
  `slug`            VARCHAR(200) NOT NULL,
  `title`           VARCHAR(200) NOT NULL,
  `description`     TEXT NULL,
  `parent_forum_id` CHAR(36)     NULL,
  `path`            VARCHAR(1000) NOT NULL DEFAULT '',
  `depth`           TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `order_id`        INT UNSIGNED NULL,
  `is_visible`      TINYINT(1)   NOT NULL DEFAULT 1,
  `is_locked`       TINYINT(1)   NOT NULL DEFAULT 0,
  `min_group`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`      INT UNSIGNED NULL,
  `updated_at`      INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `forums_slug_unique` (`slug`),
  KEY `forums_parent_idx` (`parent_forum_id`),
  KEY `forums_order_idx` (`order_id`),
  KEY `forums_min_group_idx` (`min_group`),
  CONSTRAINT `forums_parent_fk`
    FOREIGN KEY (`parent_forum_id`) REFERENCES `forums`(`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `forums_min_group_fk`
    FOREIGN KEY (`min_group`) REFERENCES `groups`(`id`)
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function down(): void
    {
        try { DB::statement("ALTER TABLE `forums` DROP FOREIGN KEY `forums_parent_fk`"); } catch (\Throwable $e) {}
        try { DB::statement("ALTER TABLE `forums` DROP FOREIGN KEY `forums_min_group_fk`"); } catch (\Throwable $e) {}
        DB::statement("DROP TABLE IF EXISTS `forums`");
    }
};
