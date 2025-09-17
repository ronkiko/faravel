<?php // v0.4.133
/* app/Http/Controllers/Forum/Pages/ForumIndexAction.php
Purpose: GET /forum/ — индекс форума: тонкий контроллер, получает список категорий,
         собирает ForumIndexPageVM и рендерит представление.
FIX: Переведён на контракт композера: вместо готового $layout передаём
     layout_overrides с nav_active='forum' и title. FlashVM — массив.
*/
namespace App\Http\Controllers\Forum\Pages;

use Faravel\Http\Request;
use Faravel\Http\Response;
use App\Services\Forum\CategoryQueryService;
use App\Http\ViewModels\Forum\ForumIndexPageVM;
use App\Http\ViewModels\Layout\FlashVM;

final class ForumIndexAction
{
    /**
     * Индекс форума: список видимых категорий.
     *
     * @param Request $request Текущий HTTP-запрос.
     * @pre none.
     * @side-effects Чтение сессии (FlashVM); чтение БД в сервисе.
     * @return Response HTML-ответ 200.
     * @throws \Throwable Пробрасывает ошибки сервисов/рендера.
     * @example GET /forum/
     */
    public function __invoke(Request $request): Response
    {
        $svc = new CategoryQueryService();

        /** @var array<int,array{id:string,slug:string,title:string,description:?string}> $cats */
        $cats = $svc->listVisibleCategories(200);

        $pageVM = ForumIndexPageVM::fromArray([
            'categories' => $cats,
        ]);

        $flash = FlashVM::fromSession($request->session())->toArray();

        // ВАЖНО: только overrides, сборку лэйаута делает композер.
        return response()->view('forum.index', [
            'vm'                => $pageVM->toArray(),
            'layout_overrides'  => [
                'title'      => 'Форум',
                'nav_active' => 'forum',
            ],
            'flash'             => $flash,
        ]);
    }
}
