DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id CHAR(36) PRIMARY KEY, -- UUIDv4
    username VARCHAR(50) NOT NULL UNIQUE,
    password CHAR(60) NOT NULL, -- bcrypt hash
    registered INT UNSIGNED NOT NULL,  -- Days since epoch
    reputation INT UNSIGNED NOT NULL DEFAULT 0,
    group_id TINYINT UNSIGNED DEFAULT 1,
    last_visit INT UNSIGNED DEFAULT NULL,
    last_post INT UNSIGNED DEFAULT NULL,
    role_id TINYINT NOT NULL DEFAULT 1, -- может быть отрицательным (например, -1 = banned)
    language TINYINT UNSIGNED NOT NULL DEFAULT 1,
    title VARCHAR(50) DEFAULT NULL, -- Звание пользователя (текстовое)
    style TINYINT UNSIGNED DEFAULT 0,
    signature VARCHAR(255) DEFAULT NULL,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- Внешние ключи
    CONSTRAINT fk_user_group FOREIGN KEY (group_id) REFERENCES groups(id),
    CONSTRAINT fk_user_role FOREIGN KEY (role_id) REFERENCES roles(id)
) CHARACTER SET utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- insert admin user (admin:adminadmin)
INSERT INTO `users` (`id`, `username`, `password`, `registered`, `reputation`, `group_id`, `last_visit`, `last_post`, `role_id`, `language`, `title`, `style`, `signature`, `last_updated`) VALUES
('1bd5d75c-655c-427f-a424-339c8840f799',	'admin',	'$2y$10$TB8Tdqym2B5aoIPIfLOTt.yUk/6133qL.tHVOsyJoOs4qh1AISEnS',	20293,	0,	1,	NULL,	NULL,	1,	1,	NULL,	0,	NULL,	'2025-07-24 05:09:14');

