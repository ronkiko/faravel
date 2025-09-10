<?php // v0.3.45
/**
 * Abilities — базовые права доступа (модерация/админка/системные действия).
 *
 * Что делает миграция:
 * - Создаёт таблицу `abilities`.
 * - Добавляет FK `abilities.min_role` → `roles.id` (CASCADE on update, RESTRICT on delete).
 *
 * Где используется:
 * - Сервис: app/Services/Auth/AbilityService.php (проверки, кэш).
 * - Провайдер: app/Providers/AbilityServiceProvider.php (регистрация прав для Gate).
 * - Админка: app/Http/Controllers/AdminAbilityController.php + resources/views/admin/abilities/*
 * - Данные: database/seeders/AbilitiesSeeder.php (базовые права).
 *
 * Как запускать:
 * - Через «Гефест» после наличия таблицы/данных `roles`.
 */

use Faravel\Support\Facades\DB;

return new class {
    public function up()
    {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS `abilities` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `label` VARCHAR(100) NULL,
  `description` TEXT NULL,
  `min_role` TINYINT NOT NULL DEFAULT 0,
  `created_at` INT UNSIGNED NULL,
  `updated_at` INT UNSIGNED NULL,
  INDEX `abilities_min_role_idx` (`min_role`),
  CONSTRAINT `abilities_min_role_fk`
    FOREIGN KEY (`min_role`) REFERENCES `roles`(`id`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        DB::connection()->exec($sql);
    }

    public function down()
    {
        DB::connection()->exec('DROP TABLE IF EXISTS `abilities`');
    }
};
