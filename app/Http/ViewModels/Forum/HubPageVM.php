<?php // v0.4.4
/* app/Http/ViewModels/Forum/HubPageVM.php
Purpose: ViewModel страницы хаба: нормализует данные под строгий Blade (links, флаги, when)
         и предоставляет meta для лейаута (title, breadcrumbs) без логики во Blade.
FIX: Заменена локальная humanizeAgo() на общий App\Support\Format\TimeFormatter::humanize().
*/

namespace App\Http\ViewModels\Forum;

use App\Support\Format\TimeFormatter;

final class HubPageVM
{
    /** @var array<string,mixed> */
    private array $data = [];

    /**
     * Factory from controller/service arrays.
     *
     * @param array<string,mixed> $in
     * @return self
     */
    public static function fromArray(array $in): self
    {
        $vm = new self();

        $tag   = (array)($in['tag'] ?? []);
        $slug  = (string)($tag['slug'] ?? '');
        $title = (string)($tag['title'] ?? $slug);
        $sort  = (string)($in['sort']['key'] ?? 'last');
        if (!in_array($sort, ['last', 'new', 'posts'], true)) {
            $sort = 'last';
        }

        $pager = (array)($in['pager'] ?? []);
        $page  = (int)($pager['page'] ?? 1);
        $pages = (int)($pager['pages'] ?? 1);
        $page  = $page > 0 ? $page : 1;
        $pages = $pages > 0 ? $pages : 1;

        $baseUrl = '/forum/f/' . $slug . '/';

        // Normalize topics: URL and 'when'
        $now    = time();
        $topics = [];
        foreach ((array)($in['topics'] ?? []) as $t) {
            $a         = (array)$t;
            $slugOrId  = (string)($a['slug'] ?? '');
            if ($slugOrId === '') {
                $slugOrId = (string)($a['id'] ?? '');
            }
            $url = (string)($a['url'] ?? ('/forum/t/' . $slugOrId . '/'));

            $ts   = isset($a['last_post_at']) ? (int)$a['last_post_at'] : null;
            $when = (string)($a['when'] ?? TimeFormatter::humanize($ts, $now));

            $topics[] = [
                'id'          => (string)($a['id'] ?? ''),
                'slug'        => (string)($a['slug'] ?? ''),
                'title'       => (string)($a['title'] ?? ('Тема #' . $slugOrId)),
                'posts_count' => (int)($a['posts_count'] ?? 0),
                'url'         => $url,
                'when'        => $when,
                'pinned'      => (int)($a['pinned'] ?? 0),
            ];
        }

        $hasTopics = !empty($topics);
        $hasPages  = $pages > 1;
        $hasPrev   = $page > 1;
        $hasNext   = $page < $pages;

        $vm->data = [
            'meta'       => [
                'title'            => 'Хаб: ' . $title,
                'has_breadcrumbs'  => 1,
                'breadcrumbs'      => [
                    ['label' => 'Форум', 'url' => '/forum', 'has_url' => 1, 'sep_before' => 0],
                    ['label' => $title,  'url' => '',       'has_url' => 0, 'sep_before' => 1],
                ],
            ],
            'tag'        => ['slug' => $slug, 'title' => $title],
            'has_topics' => $hasTopics ? 1 : 0,
            'topics'     => $topics,
            'links'      => [
                'sort_last'  => $baseUrl . '?sort=last',
                'sort_new'   => $baseUrl . '?sort=new',
                'sort_posts' => $baseUrl . '?sort=posts',
                'prev'       => $hasPrev ? $baseUrl . '?sort=' . $sort . '&page=' . ($page - 1) : '',
                'next'       => $hasNext ? $baseUrl . '?sort=' . $sort . '&page=' . ($page + 1) : '',
            ],
            'pager'      => [
                'has_pages' => $hasPages ? 1 : 0,
                'has_prev'  => $hasPrev ? 1 : 0,
                'has_next'  => $hasNext ? 1 : 0,
                'page'      => $page,
                'pages'     => $pages,
            ],
            'sort'       => ['key' => $sort],
        ];

        return $vm;
    }

    /**
     * Export data for Blade.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
