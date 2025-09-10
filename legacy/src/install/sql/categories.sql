DROP TABLE IF EXISTS `categories`;

CREATE TABLE `categories` (
  `id` CHAR(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` TEXT COLLATE utf8mb4_unicode_ci,
  `order_id` TINYINT(3) UNSIGNED DEFAULT NULL,
  `is_visible` TINYINT(1) DEFAULT '0',
  `min_group` TINYINT UNSIGNED NOT NULL DEFAULT '1',  -- 👈 тип теперь совпадает с groups.id
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  CONSTRAINT `fk_categories_min_group`
    FOREIGN KEY (`min_group`) REFERENCES `groups`(`id`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

