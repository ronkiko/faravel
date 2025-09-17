<?php // v0.4.135
/* app/Http/Controllers/Forum/Pages/ShowCategoryAction.php
Purpose: GET /forum/c/{category_slug} — страница категории: тонкий контроллер,
         вызывает CategoryQueryService, формирует CategoryPageVM и рендерит Blade.
FIX: Убрана ручная сборка $layout. Передаём layout_overrides с nav_active='forum'
     и title. FlashVM — массив. Контракт ID категории — string (UUID).
*/
namespace App\Http\Controllers\Forum\Pages;

use Faravel\Http\Request;
use Faravel\Http\Response;
use App\Services\Forum\CategoryQueryService;
use App\Http\ViewModels\Forum\CategoryPageVM;
use App\Http\ViewModels\Layout\FlashVM;

final class ShowCategoryAction
{
    /**
     * Показ страницы категории.
     *
     * @param Request $request       Текущий HTTP-запрос.
     * @param string  $category_slug Slug категории; непустой.
     * @pre $category_slug !== ''.
     * @side-effects Чтение сессии (FlashVM); чтение БД внутри сервиса.
     * @return Response HTML-ответ (200) или 404.
     * @throws \Throwable Ошибки БД/рендера пробрасываются.
     * @example GET /forum/c/test/
     */
    public function __invoke(Request $request, string $category_slug): Response
    {
        $svc = new CategoryQueryService();

        $category = $svc->findCategoryBySlug($category_slug);
        if (!$category) {
            return response('Категория не найдена', 404);
        }

        $hubs = $svc->listHubsForCategory((string) $category['id']);

        $vm = CategoryPageVM::fromArray([
            'category' => [
                'slug'        => (string)($category['slug'] ?? ''),
                'title'       => (string)($category['title'] ?? ''),
                'description' => (string)($category['description'] ?? ''),
            ],
            'hubs' => $hubs,
        ]);

        $flash = FlashVM::fromSession($request->session())->toArray();

        return response()->view('forum.category', [
            'vm'                => $vm->toArray(),
            'layout_overrides'  => [
                'title'      => 'Категория: ' . (string)($category['title'] ?? ''),
                'nav_active' => 'forum',
            ],
            'flash'             => $flash,
        ]);
    }
}
