<?php // v0.4.4
/* app/Services/Forum/TopicQueryService.php
Purpose: Сервис выборок для страницы темы.
FIX: listTagsForTopic возвращает slug+title (и цвет/активность), порядок — по времени
     привязки; добавлен findTopicBySlugOrId().
*/
namespace App\Services\Forum;

use Faravel\Support\Facades\DB;

final class TopicQueryService
{
    /** @return array<string,mixed>|null */
    public function findTopicBySlugOrId(string $topicIdOrSlug): ?array
    {
        $row = DB::table('topics')->where('slug', '=', $topicIdOrSlug)->first();
        if (!$row) {
            $row = DB::table('topics')->where('id', '=', $topicIdOrSlug)->first();
        }
        return $row ? (array)$row : null;
    }

    /** @return array<string,mixed>|null */
    public function findCategoryLight(string $categoryId): ?array
    {
        if ($categoryId === '') return null;
        $row = DB::table('categories')
            ->select(['id','slug','title'])
            ->where('id','=', $categoryId)
            ->first();
        return $row ? (array)$row : null;
    }

    /** @return array<int,array<string,mixed>> */
    public function listPosts(string $topicId, int $limit = 100): array
    {
        $limit = \max(1, \min(500, $limit));
        $rows = DB::table('posts')
            ->where('topic_id','=', $topicId)
            ->where('is_deleted','=', 0)
            ->orderBy('created_at','ASC')
            ->limit($limit)
            ->get();

        $out = [];
        foreach ($rows ?? [] as $r) {
            $out[] = (array)$r;
        }
        return $out;
    }

    /** @return array<string,array<string,mixed>> */
    public function fetchByIds(string $table, string $pk, array $ids, array $columns = ['*']): array
    {
        $out = [];
        foreach ($ids as $id) {
            $id = (string)$id;
            if ($id === '') continue;
            $qb = DB::table($table);
            if ($columns !== ['*']) $qb = $qb->select($columns);
            $row = $qb->where($pk,'=',$id)->first();
            if ($row) $out[$id] = (array)$row;
        }
        return $out;
    }

    /** @return array<int,string> */
    public function pluckUserIds(array $posts): array
    {
        $ids = [];
        foreach ($posts as $p) {
            $u = (string)($p['user_id'] ?? '');
            if ($u !== '') $ids[$u] = true;
        }
        return \array_values(\array_keys($ids));
    }

    /** @return array<int,int> */
    public function pluckGroupIds(array $users): array
    {
        $ids = [];
        foreach ($users as $u) {
            $g = (int)($u['group_id'] ?? 0);
            if ($g > 0) $ids[$g] = true;
        }
        return \array_values(\array_keys($ids));
    }

    /** @return array<string,int> */
    public function countPostsByUserFromArray(array $posts): array
    {
        $map = [];
        foreach ($posts as $p) {
            $uid = (string)($p['user_id'] ?? '');
            if ($uid !== '') $map[$uid] = ($map[$uid] ?? 0) + 1;
        }
        return $map;
    }

    /**
     * Теги темы (taggables → tags) — для хлебных крошек берём первый.
     * @return array<int,array{slug:string,title:string,color:string,is_active:int}>
     */
    public function listTagsForTopic(string $topicId): array
    {
        $rows = DB::table('taggables as tg')
            ->join('tags as t','t.id','=','tg.tag_id')
            ->select(['t.slug','t.title','t.color','t.is_active','tg.created_at'])
            ->where('tg.entity','=','topic')
            ->where('tg.topic_id','=', $topicId)
            ->orderBy('tg.created_at','asc')
            ->get();

        $out = [];
        foreach ($rows ?? [] as $r) {
            $a = (array)$r;
            $out[] = [
                'slug'      => (string)($a['slug'] ?? ''),
                'title'     => (string)($a['title'] ?? ''),
                'color'     => (string)($a['color'] ?? ''),
                'is_active' => (int)($a['is_active'] ?? 0),
            ];
        }
        return $out;
    }
}
