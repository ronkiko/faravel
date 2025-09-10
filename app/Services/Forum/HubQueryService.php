<?php // v0.4.6
/* app/Services/Forum/HubQueryService.php
Purpose: Сервис выборок для хабов/тегов: поиск тега и получение ленты тем по тегу.
FIX: Введён единый контракт topicsForTag() + прокси к легаси-методам
     (listTopicsByTag/getTopicsForTag/fetchTopicsForTag) с нормализацией pager.
*/
namespace App\Services\Forum;

use Faravel\Support\Facades\DB;

final class HubQueryService
{
    /**
     * Найти тег по slug.
     *
     * @param string $slug Непустой slug.
     * @pre $slug !== ''.
     * @side-effects Чтение БД.
     * @return array<string,mixed>|null
     * @throws \Throwable При ошибках подключения/SQL.
     * @example $tag = $svc->findTagBySlug('php');
     */
    public function findTagBySlug(string $slug): ?array
    {
        $row = DB::table('tags')->where('slug', '=', $slug)->first();
        return $row ? (array)$row : null;
    }

    /**
     * Унифицированный контракт получения ленты тем для тега.
     * Делегирует к существующим реализациям (совместимость с легаси):
     *  - listTopicsByTag(string $tagId, int $page, int $perPage, string $sortKey)
     *  - getTopicsForTag(...)
     *  - fetchTopicsForTag(...)
     *
     * Если подходящего метода нет — возвращает пустую ленту с корректным pager,
     * чтобы контроллер/вью не падали.
     *
     * @param string $tagId    UUID/ID тега; непустой.
     * @param int    $page     Номер страницы (>=1).
     * @param int    $perPage  Размер страницы (>=1).
     * @param string $sortKey  Ключ сортировки: 'last'|'new'|'posts', и т.п.
     * @pre $tagId !== ''; $page>=1; $perPage>=1.
     * @side-effects Возможен доступ к БД в делегате; сам метод I/O не делает.
     * @return array{
     *   items: array<int,array<string,mixed>>,
     *   pager: array{page:int,per_page:int,pages:int,total:int}
     * }
     * @throws \Throwable Делегируемые методы могут выбросить исключение.
     * @example $q = $svc->topicsForTag($id, 1, 20, 'last');
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

                $items   = (array)($res['items'] ?? []);
                $pagerIn = (array)($res['pager'] ?? []);
                $total   = isset($pagerIn['total'])
                    ? (int)$pagerIn['total']
                    : (int)($res['total'] ?? 0);
                $pages   = isset($pagerIn['pages'])
                    ? (int)$pagerIn['pages']
                    : (int)\max(1, \ceil($total / \max(1, $perPage)));

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

        // Фолбэк: корректная пустая лента — безопасно для контроллера/вью.
        return [
            'items' => [],
            'pager' => [
                'page'     => \max(1, $page),
                'per_page' => \max(1, $perPage),
                'pages'    => 1,
                'total'    => 0,
            ],
        ];
    }

    // TODO(FORUM-HUB): реализовать SQL-фолбэк внутри topicsForTag()
    // (join taggables→topics, сортировки last/new/posts). См. TODO.md.
}
