<?php // v0.3.1
/*
database/migrations/2025_08_23_001100_topics_add_counters.php v0.3.1
Назначение: добавить счётчики/мета к темам (posts_count, last_post_id, last_post_at).
FIX: индексы для last_post_at.
*/

use Faravel\Support\Facades\DB;

return new class {
    public function up()
    {
        DB::connection()->exec(<<<SQL
ALTER TABLE topics
  ADD COLUMN posts_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER slug,
  ADD COLUMN last_post_id CHAR(36) NULL AFTER posts_count,
  ADD COLUMN last_post_at INT UNSIGNED NULL AFTER last_post_id;

CREATE INDEX idx_topics_last_post_at ON topics (last_post_at);
SQL);
    }

    public function down()
    {
        DB::connection()->exec(<<<SQL
ALTER TABLE topics
  DROP INDEX idx_topics_last_post_at,
  DROP COLUMN last_post_at,
  DROP COLUMN last_post_id,
  DROP COLUMN posts_count;
SQL);
    }
};
