<?php // v0.4.141
/* app/Http/Controllers/Forum/Pages/ShowHubAction.php
Purpose: GET /forum/f/{tag_slug} — страница хаба/тега: тонкий контроллер, дергает
         HubQueryService, собирает HubPageVM и отдаёт Blade-представление.
FIX: Добавлен метод show() для совместимости с маршрутом [Class,'show']; подтверждена
     установка layout_overrides['nav_active']='forum'. Расширены PHPDoc.
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
    /** @var \App\Services\Forum\HubQueryService */
    private \App\Services\Forum\HubQueryService $svc;

    /**
     * DI: сервис чтения хабов/тем.
     *
     * @param \App\Services\Forum\HubQueryService $svc Экземпляр сервиса.
     */
    public function __construct(\App\Services\Forum\HubQueryService $svc)
    {
        $this->svc = $svc;
    }

    /**
     * Совместимость с роутом вида [Class,'show'].
     *
     * Summary: обёртка, перенаправляет в основной __invoke().
     *
     * @param Request $request  Текущий HTTP-запрос.
     * @param string  $tag_slug Слаг хаба; непустой.
     * @pre $tag_slug !== ''.
     * @return Response
     */
    public function show(Request $request, string $tag_slug): Response
    {
        return $this->__invoke($request, $tag_slug);
    }

    /**
     * Показ страницы хаба/тега.
     *
     * Controller → Service → VM → View. Контроллер только координирует слои.
     *
     * @param Request $request  Текущий HTTP-запрос (локаль/сессия/пользователь).
     * @param string  $tag_slug Слаг тега; непустой.
     * @pre $tag_slug !== ''; $request->query('sort') ∈ {'last','new','posts'} или 'last'.
     * @side-effects Чтение сессии (FlashVM); чтение БД внутри HubQueryService.
     * @return Response HTML-ответ (200) или 404.
     * @throws \Throwable Ошибки сервиса/рендера пробрасываются.
     * @example GET /forum/f/linux/
     */
    public function __invoke(Request $request, string $tag_slug): Response
    {
        $svc = $this->svc;

        /** @var array<string,mixed>|null $tag */
        $tag = $svc->findTagBySlug($tag_slug);
        if (!$tag) {
            return response('Хаб не найден', 404);
        }

        $page = \max(1, (int) $request->query('page', 1));
        $sort = (string) $request->query('sort', 'last');
        if (!\in_array($sort, ['last', 'new', 'posts'], true)) {
            $sort = 'last';
        }

        /** @var array{items:array<int,array<string,mixed>>,pager:array<string,int>} $list */
        $list = $svc->topicsForTag((string) $tag['id'], $page, 20, $sort);

        $vm = HubPageVM::fromArray([
            'tag'    => [
                'slug'  => (string) $tag['slug'],
                'title' => (string) ($tag['title'] ?? $tag['slug']),
            ],
            'topics' => $list['items'],
            'pager'  => $list['pager'],
            'sort'   => ['key' => $sort],
        ]);

        $flash = FlashVM::fromSession($request->session())->toArray();

        return response()->view('forum.hub', [
            'vm'               => $vm->toArray(),
            'layout_overrides' => [
                'title'      => 'Хаб: ' . (string) ($tag['title'] ?? $tag['slug']),
                'nav_active' => 'forum',
            ],
            'flash'            => $flash,
        ]);
    }
}
