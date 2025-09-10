<?php

use Faravel\Support\Facades\DB;

return new class {
    public function up()
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS posts (
    id CHAR(36) NOT NULL PRIMARY KEY,
    topic_id CHAR(36) NOT NULL,
    user_id CHAR(36) NOT NULL,
    content TEXT NOT NULL,
    created_at INT UNSIGNED NOT NULL,
    CONSTRAINT fk_posts_topic FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE,
    CONSTRAINT fk_posts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        DB::connection()->exec($sql);
    }

    public function down()
    {
        DB::connection()->exec('DROP TABLE IF EXISTS posts');
    }
};