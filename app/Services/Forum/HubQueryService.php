<?php // v0.4.10
/* app/Services/Forum/HubQueryService.php
Purpose: Сервис выборок для хабов/тегов: поиск тега и получение ленты тем по тегу.
FIX: Контракт topicsForTag() оставлен как string $tagId (UUID). Никаких доп. изменений.
*/

declare(strict_types=1);

namespace App\Services\Forum;

use Faravel\Support\Facades\DB;

final class HubQueryService
{
    /**
     * Найти тег по slug.
     *
     * @param string $slug Непустой slug.
     *
     * Preconditions:
     *  - $slug !== ''.
     *
     * Side effects:
     *  - Чтение БД.
     *
     * @return array<string,mixed>|null
     *
     * @throws \Throwable При ошибках подключения/SQL.
     * @example $tag = $svc->findTagBySlug('php');
     */
    public function findTagBySlug(string $slug): ?array
    {
        $row = DB::table('tags')->where('slug', '=', $slug)->first();
        return $row ? (array) $row : null;
    }

    /**
     * Унифицированная лента тем по ID тега.
     *
     * Делегирует в легаси-метод, если он существует, иначе использует SQL-фолбэк
     * через taggables(entity='topic') → topics. Гарантирует единый формат pager/items.
     *
     * @param string $tagId    UUID/ID тега; непустой.
     * @param int    $page     Номер страницы (>=1).
     * @param int    $perPage  Размер страницы (1..100).
     * @param string $sortKey  'last'|'new'|'posts' (иное → 'last').
     *
     * Preconditions:
     *  - $tagId !== ''; $page >= 1; 1 <= $perPage <= 100.
     *
     * Side effects:
     *  - Чтение БД (count + select).
     *
     * @return array{
     *   items: array<int,array<string,mixed>>,
     *   pager: array{page:int,per_page:int,pages:int,total:int}
     * }
     *
     * @throws \Throwable Исключения БД пробрасываются.
     *
     * @example $q = $svc->topicsForTag($uuid, 1, 20, 'last');
     */
    public function topicsForTag(
        string $tagId,
        int $page = 1,
        int $perPage = 20,
        string $sortKey = 'last'
    ): array {
        foreach (['listTopicsByTag', 'getTopicsForTag', 'fetchTopicsForTag'] as $m) {
            if (\method_exists($this, $m)) {
                /** @var array{items:array<int,array<string,mixed>>,pager?:array,total?:int} $res */
                $res = $this->$m($tagId, $page, $perPage, $sortKey);

                $items   = (array) ($res['items'] ?? []);
                $pagerIn = (array) ($res['pager'] ?? []);
                $total   = isset($pagerIn['total'])
                    ? (int) $pagerIn['total']
                    : (int) ($res['total'] ?? 0);
                $pages   = isset($pagerIn['pages'])
                    ? (int) $pagerIn['pages']
                    : (int) \max(1, \ceil($total / \max(1, $perPage)));

                return [
                    'items' => $items,
                    'pager' => [
                        'page'     => \max(1, $page),
                        'per_page' => \max(1, $perPage),
                        'pages'    => $pages,
                        'total'    => $total,
                    ],
                ];
            }
        }

        // --- SQL фолбэк: taggables (entity='topic') → topics
        $page    = \max(1, $page);
        $perPage = ($perPage >= 1 && $perPage <= 100) ? $perPage : 20;

        $total = (int) DB::table('taggables')
            ->where('tag_id', '=', $tagId)
            ->where('entity', '=', 'topic')
            ->count();

        $pages  = (int) \max(1, \ceil($total / \max(1, $perPage)));
        $page   = \min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $qb = DB::table('taggables AS tg')
            ->join('topics AS t', 't.id', '=', 'tg.topic_id')
            ->select([
                't.id',
                't.slug',
                't.title',
                't.posts_count',
                't.created_at',
                't.updated_at',
                't.last_post_at',
            ])
            ->where('tg.tag_id', '=', $tagId)
            ->where('tg.entity', '=', 'topic');

        $sortKey = \in_array($sortKey, ['last', 'new', 'posts'], true) ? $sortKey : 'last';
        if ($sortKey === 'new') {
            $qb->orderBy('t.created_at', 'DESC');
        } elseif ($sortKey === 'posts') {
            $qb->orderBy('t.posts_count', 'DESC')->orderBy('t.updated_at', 'DESC');
        } else {
            $qb->orderBy('t.last_post_at', 'DESC')->orderBy('t.updated_at', 'DESC');
        }

        $rows = $qb->limit($perPage)->offset($offset)->get();

        $items = [];
        foreach ($rows as $r) {
            $a = (array) $r;
            $slugOrId = (string) ($a['slug'] ?? '');
            if ($slugOrId === '') {
                $slugOrId = (string) ($a['id'] ?? '');
            }
            $a['url'] = '/forum/t/' . $slugOrId . '/';
            $items[]  = $a;
        }

        return [
            'items' => $items,
            'pager' => [
                'page'     => $page,
                'per_page' => $perPage,
                'pages'    => $pages,
                'total'    => $total,
            ],
        ];
    }
}
