<?php // v0.1.5
/* app/Services/Forum/CategoryQueryService.php — v0.1.5
Назначение: выборки для страниц категорий/хабов (по slug), список категорий, топ-теги.
FIX: добавлен findCategoryBySlug(); экшены переведены на чистый slug.
*/
namespace App\Services\Forum;

use Faravel\Support\Facades\DB;

final class CategoryQueryService
{
    public function findCategoryBySlug(string $slug): ?array
    {
        $row = DB::table('categories')->where('slug', '=', $slug)->first();
        return $row ? (array)$row : null;
    }
/* // deprecated
    public function findCategoryBySlugOrId(string $slugOrId): ?array
    {
        $row = DB::table('categories')->where('slug', '=', $slugOrId)->first();
        if (!$row) $row = DB::table('categories')->where('id', '=', $slugOrId)->first();
        return $row ? (array)$row : null;
    }
*/
    /** @return array<int,array{id:string,slug:string,title:string,description:?string}> */
    public function listVisibleCategories(int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));
        $rows = DB::table('categories')
            ->where('is_visible', '=', 1)
            ->orderBy('(order_id IS NULL)', 'ASC')
            ->orderBy('order_id', 'ASC')
            ->orderBy('title', 'ASC')
            ->limit($limit)
            ->get();

        $out = [];
        foreach ($rows ?? [] as $r) {
            $a = (array)$r;
            $out[] = [
                'id'          => (string)($a['id'] ?? ''),
                'slug'        => (string)($a['slug'] ?? ''),
                'title'       => (string)($a['title'] ?? ''),
                'description' => (string)($a['description'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * Топ-теги (хабы) для категории (вход — category_id).
     * @return array<int,array{
     *   slug:string,title:string,color:string,is_active:int,
     *   topics_count:int,last_activity_at:int,position:?int
     * }>
     */
    public function topTagsForCategory(string $categoryId, int $limit = 10): array
    {
        $limit = max(1, min(50, (int)$limit));

        $rows = DB::table('category_tag ct')
            ->join('tags t', 't.id', '=', 'ct.tag_id')
            ->leftJoinOn('tag_stats s', 's.category_id = ct.category_id AND s.tag_id = ct.tag_id')
            ->where('ct.category_id', '=', $categoryId)
            ->select([
                't.slug',
                't.title',
                't.color',
                't.is_active',
                'COALESCE(s.topics_count, 0) AS topics_count',
                'COALESCE(s.last_activity_at, 0) AS last_activity_at',
                'ct.position',
            ])
            ->orderBy('(ct.position IS NULL)', 'ASC')
            ->orderBy('ct.position', 'ASC')
            ->orderBy('COALESCE(s.last_activity_at, 0)', 'DESC')
            ->orderBy('t.title', 'ASC')
            ->limit($limit)
            ->get();

        $out = [];
        foreach ($rows ?? [] as $r) {
            $arr = (array)$r;
            $out[] = [
                'slug'             => (string)($arr['slug'] ?? ''),
                'title'            => (string)($arr['title'] ?? ''),
                'color'            => (string)($arr['color'] ?? ''),
                'is_active'        => (int)($arr['is_active'] ?? 0),
                'topics_count'     => (int)($arr['topics_count'] ?? 0),
                'last_activity_at' => (int)($arr['last_activity_at'] ?? 0),
                'position'         => array_key_exists('position', $arr) && $arr['position'] !== null
                                        ? (int)$arr['position'] : null,
            ];
        }
        return $out;
    }
}
