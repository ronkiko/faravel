<?php // v0.3.13
/*
TagReadService — чтение данных «хабов/тегов».
Контракты под Blade/контроллер:
- topTagsByCategory($categoryId,$limit) → [{id,slug,title,color,topics_count}]
- listTopicsByTags($categoryId?, $slugs, $page, $per) →
    ['items'=>[{id,slug,title,posts_count,last_post_at,created_at}], 'total','pages']
- topicTagPills($topicId) → [{slug,title,color,is_active}]
- getTagById($tagId) → object|null (включая is_active)
- listTopicsByTagId($tagId,$page,$per) →
    ['items'=>[{id,slug,title,posts_count,last_post_at,created_at}], 'total','pages']
- ensureTopicHasTag($topicId,$tagId) → bool
*/

namespace App\Services\Tag;

use Faravel\Support\Facades\DB;

class TagReadService
{
    /** @return array<int, array{id:string,slug:string,title:string,color:?string,topics_count:int}> */
    public function topTagsByCategory(string $categoryId, int $limit = 48): array
    {
        return DB::select(
            "SELECT
                 t.id          AS id,
                 t.slug        AS slug,
                 t.title       AS title,
                 t.color       AS color,
                 s.topics_count AS topics_count
             FROM tag_stats s
             JOIN tags t ON t.id = s.tag_id
             WHERE s.category_id = ? AND t.is_active = 1
             ORDER BY s.topics_count DESC, t.title ASC
             LIMIT ?",
            [$categoryId, $limit]
        );
    }

    /**
     * AND-комбинация активных тегов.
     * @return array{items: array<int,array{id:string,slug:?string,title:string,posts_count:int,last_post_at:?int,created_at:int}>, total:int, pages:int}
     */
    public function listTopicsByTags(?string $categoryId, array $slugs, int $page = 1, int $perPage = 20): array
    {
        $slugs = array_values(array_unique(array_filter($slugs, 'strlen')));
        if (!$slugs) return ['items'=>[], 'total'=>0, 'pages'=>0];

        $N       = count($slugs);
        $offset  = ($page - 1) * $perPage;
        $catSql  = $categoryId ? "AND tp.category_id = ?" : "";

        // total
        $paramsTotal = array_merge($slugs, $categoryId ? [$categoryId] : []);
        $rows = DB::select(
            "SELECT COUNT(*) AS cnt FROM (
                 SELECT tp.id
                 FROM topics tp
                 JOIN taggables g ON g.topic_id = tp.id AND g.entity = 'topic'
                 JOIN tags t      ON t.id = g.tag_id AND t.is_active = 1
                 WHERE t.slug IN (" . implode(',', array_fill(0, $N, '?')) . ")
                 $catSql
                 GROUP BY tp.id
                 HAVING COUNT(DISTINCT t.slug) = $N
             ) x",
            $paramsTotal
        );
        $total = (int) ( ($rows[0]->cnt ?? $rows[0]['cnt'] ?? 0) );

        // items
        $paramsItems = array_merge($slugs, $categoryId ? [$categoryId] : [], [$perPage, $offset]);
        $items = DB::select(
            "SELECT tp.id, tp.slug, tp.title, tp.posts_count, tp.last_post_at, tp.created_at
             FROM topics tp
             JOIN taggables g ON g.topic_id = tp.id AND g.entity = 'topic'
             JOIN tags t      ON t.id = g.tag_id AND t.is_active = 1
             WHERE t.slug IN (" . implode(',', array_fill(0, $N, '?')) . ")
             $catSql
             GROUP BY tp.id
             HAVING COUNT(DISTINCT t.slug) = $N
             ORDER BY tp.last_post_at DESC, tp.created_at DESC
             LIMIT ? OFFSET ?",
            $paramsItems
        );

        $pages = $perPage > 0 ? (int)max(1, ceil($total / $perPage)) : 1;
        return ['items'=>$items, 'total'=>$total, 'pages'=>$pages];
    }

    /** @return array<int, array{slug:string,title:string,color:?string,is_active:int}> */
    public function topicTagPills(string $topicId): array
    {
        return DB::select(
            "SELECT tg.slug AS slug, tg.title AS title, tg.color AS color, tg.is_active AS is_active
             FROM taggables g
             JOIN tags tg ON tg.id = g.tag_id
             WHERE g.topic_id = ? AND g.entity IN ('topic','post')
             GROUP BY tg.id
             ORDER BY tg.title ASC",
            [$topicId]
        );
    }

    /** Вернуть тег по UUID (включая is_active). */
    public function getTagById(string $tagId) /* : object|null */
    {
        return DB::table('tags')->where('id', '=', $tagId)->first();
    }

    /**
     * Темы по UUID тега (для /forum/h/{tagId}).
     * @return array{items: array<int,array{id:string,slug:?string,title:string,posts_count:int,last_post_at:?int,created_at:int}>, total:int, pages:int}
     */
    public function listTopicsByTagId(string $tagId, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;

        // total
        $rows = DB::select(
            "SELECT COUNT(DISTINCT tp.id) AS cnt
             FROM topics tp
             JOIN taggables g ON g.topic_id = tp.id AND g.entity = 'topic'
             WHERE g.tag_id = ?",
            [$tagId]
        );
        $total = (int) ( ($rows[0]->cnt ?? $rows[0]['cnt'] ?? 0) );

        // items (без DISTINCT; дубликаты убирает GROUP BY)
        $items = DB::select(
            "SELECT tp.id, tp.slug, tp.title, tp.posts_count, tp.last_post_at, tp.created_at
             FROM topics tp
             JOIN taggables g ON g.topic_id = tp.id AND g.entity = 'topic'
             WHERE g.tag_id = ?
             GROUP BY tp.id
             ORDER BY tp.last_post_at DESC, tp.created_at DESC
             LIMIT ? OFFSET ?",
            [$tagId, $perPage, $offset]
        );

        $pages = $perPage > 0 ? (int)max(1, ceil($total / $perPage)) : 1;
        return ['items'=>$items, 'total'=>$total, 'pages'=>$pages];
    }

    /** Проверка: принадлежит ли тема тегу (для /forum/h/{tagId}/t/{topicId}). */
    public function ensureTopicHasTag(string $topicId, string $tagId): bool
    {
        $rows = DB::select(
            "SELECT 1 AS ok
             FROM taggables
             WHERE entity = 'topic' AND topic_id = ? AND tag_id = ?
             LIMIT 1",
            [$topicId, $tagId]
        );
        return !empty($rows);
    }
}
