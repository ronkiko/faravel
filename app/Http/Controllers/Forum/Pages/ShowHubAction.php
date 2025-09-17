<?php // v0.4.139
/* app/Http/Controllers/Forum/Pages/ShowHubAction.php
Purpose: GET /forum/f/{tag_slug} — страница хаба/тега: тонкий контроллер, дергает
         HubQueryService, собирает HubPageVM и отдаёт Blade-представление.
FIX: Убрана ручная сборка $layout. Передаём layout_overrides с nav_active='forum'
     и title. FlashVM ->toArray(). ID тега — string (UUID).
*/

declare(strict_types=1);

namespace App\Http\Controllers\Forum\Pages;

use Faravel\Http\Request;
use Faravel\Http\Response;
use App\Services\Forum\HubQueryService;
use App\Http\ViewModels\Forum\HubPageVM;
use App\Http\ViewModels\Layout\FlashVM;

final class ShowHubAction
{
    /**
     * Показ страницы хаба/тега.
     *
     * @param Request $request  Текущий HTTP-запрос (локаль/сессия/пользователь).
     * @param string  $tag_slug Slug тега; непустой.
     * @pre $tag_slug !== ''.
     * @side-effects Чтение сессии (FlashVM); чтение БД внутри сервиса.
     * @return Response HTML-ответ (200) или 404.
     * @throws \Throwable Ошибки БД/рендера пробрасываются.
     * @example GET /forum/f/linux/
     */
    public function __invoke(Request $request, string $tag_slug): Response
    {
        $svc = new HubQueryService();

        $tag = $svc->findTagBySlug($tag_slug);
        if (!$tag) {
            return response('Хаб не найден', 404);
        }

        $page = \max(1, (int)$request->query('page', 1));
        $sort = (string)$request->query('sort', 'last');
        if (!\in_array($sort, ['last', 'new', 'posts'], true)) {
            $sort = 'last';
        }

        $list = $svc->topicsForTag((string)$tag['id'], $page, 20, $sort);

        $vm = HubPageVM::fromArray([
            'tag'    => [
                'slug'  => (string)$tag['slug'],
                'title' => (string)($tag['title'] ?? $tag['slug']),
            ],
            'topics' => $list['items'],
            'pager'  => $list['pager'],
            'sort'   => ['key' => $sort],
        ]);

        $flash = FlashVM::fromSession($request->session())->toArray();

        return response()->view('forum.hub', [
            'vm'                => $vm->toArray(),
            'layout_overrides'  => [
                'title'      => 'Хаб: ' . (string)($tag['title'] ?? $tag['slug']),
                'nav_active' => 'forum',
            ],
            'flash'             => $flash,
        ]);
    }
}
