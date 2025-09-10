<?php

use Faravel\Support\Facades\DB;

return new class {
    public function up(): void
    {
        // Добавляем колонку, индекс и FK — аккуратно, если уже есть, ошибки игнорируем
        try { DB::statement("ALTER TABLE `topics` ADD COLUMN `forum_id` CHAR(36) NULL AFTER `category_id`"); } catch (\Throwable $e) {}
        try { DB::statement("CREATE INDEX `topics_forum_id_idx` ON `topics` (`forum_id`)"); } catch (\Throwable $e) {}
        try {
            DB::statement(<<<'SQL'
ALTER TABLE `topics`
  ADD CONSTRAINT `topics_forum_fk`
  FOREIGN KEY (`forum_id`) REFERENCES `forums`(`id`)
  ON UPDATE CASCADE ON DELETE SET NULL
SQL);
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        try { DB::statement("ALTER TABLE `topics` DROP FOREIGN KEY `topics_forum_fk`"); } catch (\Throwable $e) {}
        try { DB::statement("DROP INDEX `topics_forum_id_idx` ON `topics`"); } catch (\Throwable $e) {}
        try { DB::statement("ALTER TABLE `topics` DROP COLUMN `forum_id`"); } catch (\Throwable $e) {}
    }
};
