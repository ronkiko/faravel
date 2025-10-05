<?php // v0.4.6
/* app/Http/ViewModels/Forum/HubPageVM.php
Purpose: ViewModel страницы хаба. Нормализует данные в примитивы для строгого Blade:
         topics[], pager, links, abilities и плоские флаги. Без доступа к БД.
FIX: Добавлены поля show_create (0|1) и create_url (string) для простого рендера кнопки.
     Исключена необходимость сложных условий в Blade.
*/

namespace App\Http\ViewModels\Forum;

use App\Support\Format\TimeFormatter;

final class HubPageVM
{
    /** @var array<string,mixed> */
    private array $data = [];

    /**
     * Сконструировать VM из массива контроллера.
     *
     * Контракт: ожидает tag, topics[], pager{page,per_page,pages,total},
     * links{self,sort_*,prev,next,create|null}, abilities.can_create.
     *
     * @param array<string,mixed> $data Контроллер→VM данные без объектов.
     *
     * Preconditions:
     * - $data['tag']['slug'] непустой string.
     * - $data['topics'] — array<array>.
     * - $data['pager'] содержит page|pages|total|per_page.
     *
     * Side effects: отсутствуют.
     *
     * @return static
     *
     * @throws \InvalidArgumentException При отсутствии обязательных полей.
     * @example См. контроллер ShowHubAction::__invoke().
     */
    public static function fromArray(array $data): static
    {
        $tag   = (array) ($data['tag'] ?? []);
        $slug  = (string) ($tag['slug'] ?? '');
        $title = (string) ($tag['title'] ?? $slug);
        if ($slug === '') {
            throw new \InvalidArgumentException('HubPageVM requires tag.slug.');
        }

        // Topics: приведение к скалярам.
        $topicsIn = \is_array($data['topics'] ?? null) ? (array) $data['topics'] : [];
        $topics   = [];
        $now      = time();
        foreach ($topicsIn as $t) {
            $a = (array) $t;
            $a['id']     = (string) ($a['id'] ?? '');
            $a['title']  = (string) ($a['title'] ?? '');
            $a['slug']   = (string) ($a['slug'] ?? '');
            $a['url']    = (string) ($a['url'] ?? ($a['slug'] ? '/forum/t/'.$a['slug'].'/' : ''));
            $ts          = (int) ($a['when'] ?? $a['created_at'] ?? $now);
            $a['when']   = TimeFormatter::humanize($ts, $now);
            $a['author'] = (string) ($a['author'] ?? ($a['user_name'] ?? ''));
            $a['posts']  = (int) ($a['posts'] ?? ($a['posts_count'] ?? 0));
            $topics[]    = $a;
        }

        // Pager.
        $pagerIn  = (array) ($data['pager'] ?? []);
        $page     = max(1, (int) ($pagerIn['page'] ?? 1));
        $perPage  = max(1, (int) ($pagerIn['per_page'] ?? 20));
        $pages    = max(1, (int) ($pagerIn['pages'] ?? 1));
        $total    = max(0, (int) ($pagerIn['total'] ?? 0));
        $hasPrev  = $page > 1 ? 1 : 0;
        $hasNext  = $page < $pages ? 1 : 0;
        $hasPages = $pages > 1 ? 1 : 0;

        // Links и abilities.
        $linksIn = (array) ($data['links'] ?? []);
        $links   = [
            'self'       => (string) ($linksIn['self'] ?? '/forum/f/'.$slug.'/'),
            'sort_last'  => (string) ($linksIn['sort_last'] ?? ''),
            'sort_new'   => (string) ($linksIn['sort_new'] ?? ''),
            'sort_posts' => (string) ($linksIn['sort_posts'] ?? ''),
            'prev'       => (string) ($linksIn['prev'] ?? ''),
            'next'       => (string) ($linksIn['next'] ?? ''),
            'create'     => $linksIn['create'] ?? null,
        ];

        $abilitiesIn = (array) ($data['abilities'] ?? []);
        $canCreate   = (bool) ($abilitiesIn['can_create'] ?? ($data['can_create'] ?? false));

        // Плоские примитивы для Blade.
        $createRaw   = \is_string($links['create']) ? (string) $links['create'] : '';
        $createUrl   = ($canCreate && $createRaw !== '') ? $createRaw : '';
        $showCreate  = $createUrl !== '' ? 1 : 0;

        $vm = new static();
        $vm->data = [
            'meta' => [
                'title'           => 'Хаб: '.$title,
                'has_breadcrumbs' => 1,
                'breadcrumbs'     => [
                    ['label' => 'Форум', 'url' => '/forum/', 'has_url' => 1, 'sep_before' => 0],
                    ['label' => $title, 'url' => $links['self'], 'has_url' => 1, 'sep_before' => 1],
                ],
            ],
            'tag'         => ['slug' => $slug, 'title' => $title],
            'topics'      => $topics,
            'links'       => $links,
            'abilities'   => ['can_create' => $canCreate ? 1 : 0],
            'pager'       => [
                'page'      => $page,
                'per_page'  => $perPage,
                'pages'     => $pages,
                'total'     => $total,
                'has_prev'  => $hasPrev,
                'has_next'  => $hasNext,
                'has_pages' => $hasPages,
            ],
            // Плоские ключи для строгого Blade:
            'show_create' => $showCreate,
            'create_url'  => $createUrl,
        ];

        return $vm;
    }

    /**
     * Экспорт массива для Blade.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
