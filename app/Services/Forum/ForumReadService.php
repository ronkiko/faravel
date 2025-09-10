<?php // v0.3.102 — ForumReadService: выборки форумов/категорий + агрегаты статистики.

namespace App\Services\Forum;

use App\Services\Schema\SchemaInspector;
use Faravel\Support\Facades\DB;

class ForumReadService
{
    public function __construct(private SchemaInspector $schema) {}

    /** Список форумов доступных пользователю внутри категории */
    public function selectForumsForCategory(string $categoryId, int $viewerRoleId): array
    {
        if (!$this->schema->hasTable('forums') || !$this->schema->hasTable('category_forum')) return [];

        $rows = DB::select(
            "SELECT f.id, f.slug, f.title, f.description, f.order_id, f.is_visible, f.is_locked, f.min_group
               FROM forums f
               JOIN category_forum cf ON cf.forum_id = f.id
              WHERE cf.category_id = ?
                AND f.is_visible = 1
                AND f.min_group <= ?
              ORDER BY cf.position IS NULL, cf.position,
                       (f.order_id IS NULL), f.order_id, f.title",
            [$categoryId, $viewerRoleId]
        );
        return array_map(fn($r)=>(array)$r, $rows);
    }

    /** Список категорий, связанных с форумом, доступных пользователю */
    public function categoriesForForum(string $forumId, int $viewerGroup): array
    {
        if (!$this->schema->hasTable('category_forum')) return [];
        $rows = DB::select(
            "SELECT c.id, c.title
               FROM categories c
               JOIN category_forum cf ON cf.category_id = c.id
              WHERE cf.forum_id = ?
                AND c.is_visible = 1
                AND c.min_group <= ?
              ORDER BY cf.position IS NULL, cf.position, c.title",
            [$forumId, $viewerGroup]
        );
        return array_map(fn($r)=>(array)$r, $rows);
    }

    /** Агрегированная статистика по форумам категории */
    public function categoryStats(array $forumIds): array
    {
        $stats = ['topics'=>[], 'posts'=>[], 'last'=>[]];
        if (empty($forumIds) || !$this->schema->hasTable('topics')) {
            return $stats;
        }

        $ph = $this->inPlaceholders(count($forumIds));

        // Кол-во тем
        $rows = DB::select(
            "SELECT forum_id, COUNT(*) c FROM topics
             WHERE forum_id IN ($ph)
             GROUP BY forum_id", $forumIds
        );
        foreach ($rows as $r){ $r=(array)$r; $stats['topics'][(string)$r['forum_id']]=(int)$r['c']; }

        // Кол-во постов
        if ($this->schema->hasTable('posts')){
            $rows = DB::select(
                "SELECT t.forum_id, COUNT(p.id) c
                   FROM posts p JOIN topics t ON t.id=p.topic_id
                  WHERE t.forum_id IN ($ph)
                  GROUP BY t.forum_id", $forumIds
            );
            foreach ($rows as $r){ $r=(array)$r; $stats['posts'][(string)$r['forum_id']]=(int)$r['c']; }
        }

        // Последнее обновление
        $rows = DB::select(
            "SELECT forum_id, MAX(updated_at) last_ts FROM topics
             WHERE forum_id IN ($ph)
             GROUP BY forum_id", $forumIds
        );
        foreach ($rows as $r){ $r=(array)$r; $stats['last'][(string)$r['forum_id']]=(int)$r['last_ts']; }

        return $stats;
    }

    private function inPlaceholders(int $n): string
    {
        return $n > 0 ? implode(',', array_fill(0, $n, '?')) : 'NULL';
    }
}
