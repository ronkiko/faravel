<?php
/* database/migrations/2025_08_24_001200_perf_indexes.php — v0.1.0
Назначение: индексы под частые запросы (хабы, темы, посты).
FIX: добавлены составные индексы и по created_at.
*/
use Faravel\Support\Facades\DB;

return new class {
    public function up(): void
    {
        DB::connection()->exec(<<<SQL
ALTER TABLE posts
  ADD INDEX idx_posts_topic_notdel_created (topic_id, is_deleted, created_at),
  ADD INDEX idx_posts_user_created (user_id, created_at);
SQL);

        DB::connection()->exec(<<<SQL
ALTER TABLE taggables
  ADD INDEX idx_taggables_tag_entity_topic (tag_id, entity, topic_id),
  ADD INDEX idx_taggables_created_at (created_at);
SQL);
    }

    public function down(): void
    {
        DB::connection()->exec(<<<SQL
ALTER TABLE posts
  DROP INDEX idx_posts_topic_notdel_created,
  DROP INDEX idx_posts_user_created;
SQL);

        DB::connection()->exec(<<<SQL
ALTER TABLE taggables
  DROP INDEX idx_taggables_tag_entity_topic,
  DROP INDEX idx_taggables_created_at;
SQL);
    }
};
