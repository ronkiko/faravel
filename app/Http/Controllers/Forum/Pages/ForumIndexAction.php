<?php // v0.4.5
/* app/Http/Controllers/Forum/Pages/ForumIndexAction.php
Purpose: GET /forum/ — индекс форума. Берёт список видимых категорий у
         CategoryQueryService, упаковывает в ForumIndexPageVM и рендерит Blade.
FIX: Убран top-level die(); добавлено пошаговое логирование START/DATA/VM/VIEW.
*/

namespace App\Http\Controllers\Forum\Pages;

use Faravel\Http\Request;
use Faravel\Http\Response;
use App\Services\Forum\CategoryQueryService;
use App\Http\ViewModels\Forum\ForumIndexPageVM;
use App\Http\ViewModels\Layout\FlashVM;
use App\Support\Logger;

final class ForumIndexAction
{
    /** @var \App\Services\Forum\CategoryQueryService */
    private \App\Services\Forum\CategoryQueryService $categories;

    /**
     * @param \App\Services\Forum\CategoryQueryService $categories Сервис витрины категорий.
     */
    public function __construct(\App\Services\Forum\CategoryQueryService $categories)
    {
        $this->categories = $categories;
    }

    /**
     * Совместимость с маршрутом [ForumIndexAction::class,'show'].
     *
     * @param Request $request
     * @return Response
     */
    public function show(Request $request): Response
    {
        return $this->__invoke($request);
    }

    /**
     * Индекс форума: подготовка VM и рендер.
     *
     * @param Request $request Текущий HTTP-запрос.
     * @return Response HTML-ответ.
     */
    public function __invoke(Request $request): Response
    {
        Logger::log('FORUM.INDEX.START', 'enter');

        try {
            /** @var array<int,array{id:string,slug:string,title:string,description:?string}> $cats */
            $cats = $this->categories->listVisibleCategories();
            Logger::log('FORUM.INDEX.DATA', 'cats=' . \count($cats));
        } catch (\Throwable $e) {
            Logger::exception('FORUM.INDEX.DATA', $e);
            return new Response('500 categories error', 500);
        }

        $cards = [];
        foreach ($cats as $c) {
            $slug = (string)($c['slug'] ?? '');
            $cards[] = [
                'id'          => (string)($c['id'] ?? ''),
                'slug'        => $slug,
                'title'       => (string)($c['title'] ?? ''),
                'description' => (string)($c['description'] ?? ''),
                'url'         => '/forum/c/' . rawurlencode($slug) . '/',
            ];
        }
        Logger::log('FORUM.INDEX.VM.PREP', 'cards=' . \count($cards));

        try {
            $vm = ForumIndexPageVM::fromArray(['categories' => $cards]);
        } catch (\Throwable $e) {
            Logger::exception('FORUM.INDEX.VM', $e, ['sample' => \array_slice($cards, 0, 1)]);
            return new Response('500 VM error', 500);
        }

        $flash = FlashVM::fromSession($request->session())->toArray();

        try {
            Logger::log('FORUM.INDEX.VIEW', 'render forum.index');
            return response()->view('forum.index', [
                'vm'               => $vm->toArray(),
                'layout_overrides' => [
                    'title'      => 'Форум',
                    'nav_active' => 'forum',
                ],
                'flash'            => $flash,
            ]);
        } catch (\Throwable $e) {
            Logger::exception('FORUM.INDEX.RENDER', $e);
            return new Response('500 render error', 500);
        }
    }
}
