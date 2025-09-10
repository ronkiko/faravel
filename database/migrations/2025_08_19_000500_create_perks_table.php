<?php // v0.3.45
/**
 * Perks — косметические «плюшки» по группам (цветные ники, рамки и т.п.).
 *
 * Что делает миграция:
 * - Создаёт таблицу `perks`.
 * - Добавляет индекс и FK `perks.min_group_id` → `groups.id` (CASCADE on update, RESTRICT on delete).
 *
 * Где используется:
 * - Сервис: app/Services/Auth/PerkService.php (проверка доступности, кэш).
 * - Админка: app/Http/Controllers/AdminPerkController.php + resources/views/admin/perks/*
 * - Данные: database/seeders/PerksSeeder.php (базовые перки).
 *
 * Важно:
 * - Перки не проходят через Gate — проверяются только PerkService.
 *
 * Как запускать:
 * - Через «Гефест» после наличия таблицы/данных `groups`.
 */

use Faravel\Support\Facades\DB;

return new class {
    public function up(): void
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS `perks` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `key` VARCHAR(100) NOT NULL,
              `label` VARCHAR(100) NULL,
              `description` TEXT NULL,
              `min_group_id` TINYINT UNSIGNED NOT NULL DEFAULT 0,
              `created_at` INT UNSIGNED NULL,
              `updated_at` INT UNSIGNED NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `perks_key_unique` (`key`),
              INDEX `perks_min_group_idx` (`min_group_id`),
              CONSTRAINT `perks_min_group_fk`
                FOREIGN KEY (`min_group_id`) REFERENCES `groups`(`id`)
                ON UPDATE CASCADE
                ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS `perks`");
    }
};
