<?php // v0.4.11
/* app/Http/Controllers/Forum/Pages/ForumIndexAction.php
Purpose: GET /forum/ — список категорий. Тонкий контроллер: получает доменные данные,
         формирует Page VM и передаёт ТОЛЬКО layout_overrides; сборку layout выполняет
         единый LayoutService через LayoutComposer.
FIX: Убрана сборка layout из контроллера. Теперь передаём только overrides
     ('title','nav_active'), а композер вызывает сервис и инжектит итоговый $layout.
*/
namespace App\Http\Controllers\Forum\Pages;

use Faravel\Http\Response;
use App\Services\Forum\CategoryQueryService;
use App\Http\ViewModels\Forum\ForumIndexPageVM;
use App\Http\ViewModels\Layout\FlashVM;

final class ForumIndexAction
{
    /**
     * Обработчик GET /forum/.
     *
     * Контроллер оркестрирует доменные сервисы и передаёт в View:
     * - плоский Page VM (данные страницы),
     * - только layout_overrides (какие ключи лэйаута переопределить).
     * Сам layout собирает LayoutService, а инъекцию делает LayoutComposer.
     *
     * Preconditions:
     * - CategoryQueryService::listVisibleCategories(int) отдаёт массив категорий.
     * - LayoutComposer подключён и делегирует сборку в LayoutService::build(...).
     *
     * Side effects: нет (сессия/пользователь читаются в LayoutService).
     *
     * @return Response
     * @throws \InvalidArgumentException При некорректных входных данных.
     * @example GET /forum/ → список категорий.
     */
    public function __invoke(): Response
    {
        // 1) Домен → Page VM (плоские, безопасные для Blade данные)
        $svc  = new CategoryQueryService();
        $cats = $svc->listVisibleCategories(200);

        $pageVM = ForumIndexPageVM::fromArray([
            'categories' => $cats,
        ]);

        // 2) Никакой сборки layout — только подсказки (overrides) для единого сервиса.
        $layoutOverrides = [
            'title'      => 'Форум',
            'nav_active' => 'forum', // обязательный ключ для подсветки активного пункта
            // при желании сюда же можно подмешивать site.* (логотип/домашняя ссылка/тайтл)
        ];

        // 3) Рендер — композер инжектит layout, собранный сервисом
        return response()->view('forum.index', [
            'vm'               => $pageVM->toArray(),
            'layout_overrides' => $layoutOverrides,
            'flash'            => FlashVM::from([]),
        ]);
    }
}
