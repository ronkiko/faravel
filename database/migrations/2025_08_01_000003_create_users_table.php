<?php

use Faravel\Support\Facades\DB;

return new class {
    public function up()
    {
        // nowdoc: доллары в тексте не интерполируются
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id CHAR(36) PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password CHAR(60) NOT NULL,
    registered INT UNSIGNED NOT NULL,
    reputation INT UNSIGNED NOT NULL DEFAULT 0,
    group_id TINYINT UNSIGNED DEFAULT 1,
    last_visit INT UNSIGNED DEFAULT NULL,
    last_post INT UNSIGNED DEFAULT NULL,
    role_id TINYINT NOT NULL DEFAULT 1,
    language TINYINT UNSIGNED NOT NULL DEFAULT 1,
    title VARCHAR(50) DEFAULT NULL,
    style TINYINT UNSIGNED DEFAULT 0,
    signature VARCHAR(255) DEFAULT NULL,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_group FOREIGN KEY (group_id) REFERENCES groups(id),
    CONSTRAINT fk_user_role FOREIGN KEY (role_id) REFERENCES roles(id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
SQL;

        DB::connection()->exec($sql);
    }

    public function down()
    {
        DB::connection()->exec('DROP TABLE IF EXISTS users');
    }
};
