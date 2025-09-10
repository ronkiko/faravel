<?php // v0.3.10
/*
app/Services/Tag/TagWriteService.php v0.3.10
Назначение: привязка тегов к теме/посту, upsert "серых" тегов при необходимости, инкремент
tag_stats для активных тегов. Рассчитан на строгое MVC: контроллер/TopicWriter создаёт тему
и пост, а этот сервис выполняет привязку и обновление статистики.
FIX: безопасные INSERT IGNORE в taggables; upsertPending создаёт неактивные теги; bumpStats
учитывает только активные теги и ведёт учёт по категориям темы.
*/

namespace App\Services\Tag;

use Faravel\Support\Facades\DB;

class TagWriteService
{
    /** Найти существующие теги по слегам, не создавая новых. slug => tag_id */
    public function findExistingBySlugs(array $slugs): array
    {
        if (!$slugs) return [];
        $in = implode(',', array_fill(0, count($slugs), '?'));
        $rows = DB::select("SELECT id, slug FROM tags WHERE slug IN ($in)", $slugs);
        $map = [];
        foreach ($rows as $r) {
            $map[(string)$r->slug] = (string)$r->id;
        }
        return $map;
    }

    /** Создать недостающие теги как "серые" (is_active=0). Возвращает slug => tag_id. */
    public function upsertPending(array $slugs, ?string $createdBy = null): array
    {
        if (!$slugs) return [];
        $now = time();
        $map = $this->findExistingBySlugs($slugs);
        foreach ($slugs as $slug) {
            if (isset($map[$slug])) continue;
            $id = DB::uuid();
            DB::insert(
                "INSERT INTO tags(id, slug, title, is_active, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, 0, ?, ?, ?)",
                [$id, $slug, $slug, $createdBy, $now, $now]
            );
            $map[$slug] = $id;
        }
        return $map;
    }

    /** Привязывает набор тегов к теме (entity='topic'), затем обновляет статистику по активным. */
    public function attachToTopic(string $topicId, array $tagIds): void
    {
        $this->attach('topic', $topicId, $topicId, $tagIds);
        $this->bumpStatsForActiveTags($topicId, $tagIds);
    }

    /** Привязывает набор тегов к посту (entity='post'), без обновления статистики. */
    public function attachToPost(string $postId, string $topicId, array $tagIds): void
    {
        $this->attach('post', $postId, $topicId, $tagIds);
    }

    private function attach(string $entity, string $entityId, ?string $topicId, array $tagIds): void
    {
        if (!$tagIds) return;
        $now = time();
        foreach ($tagIds as $tagId) {
            DB::insert(
                "INSERT IGNORE INTO taggables(tag_id, entity, entity_id, topic_id, created_at)
                 VALUES (?, ?, ?, ?, ?)",
                [$tagId, $entity, $entityId, $topicId, $now]
            );
        }
    }

    private function bumpStatsForActiveTags(string $topicId, array $tagIds): void
    {
        if (!$tagIds) return;
        $topic = DB::table('topics')->where('id', '=', $topicId)->first();
        if (!$topic) return;
        $categoryId = (string)$topic->category_id;
        $now = time();

        $in = implode(',', array_fill(0, count($tagIds), '?'));
        $rows = DB::select("SELECT id FROM tags WHERE is_active=1 AND id IN ($in)", $tagIds);
        $active = array_map(fn($r) => (string)$r->id, $rows);

        foreach ($active as $tagId) {
            DB::insert(
                "INSERT INTO tag_stats(category_id, tag_id, topics_count, last_activity_at, created_at, updated_at)
                 VALUES (?, ?, 1, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                   topics_count = topics_count + 1,
                   last_activity_at = VALUES(last_activity_at),
                   updated_at = VALUES(updated_at)",
                [$categoryId, $tagId, $now, $now, $now]
            );
        }
    }
}
