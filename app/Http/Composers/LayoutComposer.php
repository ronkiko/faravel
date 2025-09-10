<?php // v0.4.1
/* app/Http/Composers/LayoutComposer.php
Purpose: Глобальный композер лэйаута. Тонкий адаптер: берёт только overrides из View
         и делегирует сборку единому LayoutService::build(). Уважает legacy-случай,
         когда контроллер уже принёс готовый layout (__built=true).
FIX: Новый класс с правильным названием и "тонкой" логикой без дублирования сборки.
*/

namespace App\Http\Composers;

use Faravel\View\View;
use App\Services\Layout\LayoutService;

final class LayoutComposer
{
    /**
     * Inject final $layout into every rendered view.
     *
     * Why: keep blades "dumb". All layout assembly happens in LayoutService.
     * Composer only adapts controller-provided overrides to the service call.
     *
     * @param View $view
     *   View instance that may contain 'layout' or 'layout_overrides' in its data.
     *
     * Preconditions:
     * - LayoutService::build(Request,array) is available in the container.
     * - Controllers should pass only 'layout_overrides' (e.g. 'nav_active','title','site.*').
     * - If a ready-made layout with '__built'=>true is present, it's considered final.
     *
     * Side effects:
     * - Reads request via helper request() (no DB).
     * - Calls LayoutService::build() and injects its result into the view.
     *
     * @return void
     */
    public function compose(View $view): void
    {
        /** @var array<string,mixed> $data */
        $data = method_exists($view, 'getData') ? (array)$view->getData() : [];

        // 1) Respect ready-made layouts from legacy controllers.
        if (isset($data['layout']) && is_array($data['layout']) && !empty($data['layout']['__built'])) {
            if (method_exists($view, 'with')) {
                $view->with('layout', $data['layout']);
            }
            return;
        }

        // 2) Collect "lightweight" overrides (only hints for the service).
        $overrides = [];
        if (isset($data['layout_overrides']) && is_array($data['layout_overrides'])) {
            $overrides = $data['layout_overrides'];
        }

        // 3) Single source of truth — the service.
        /** @var \Faravel\Http\Request|null $request */
        $request = function_exists('request') ? request() : null;

        /** @var LayoutService $ls */
        $ls = app(LayoutService::class) ?? new LayoutService();

        $layoutVM = $ls->build($request, $overrides);
        $layout   = $layoutVM->toArray();
        $layout['__built'] = true; // mark to avoid re-assembly on nested includes

        // 4) Bind into current view (no global share).
        if (method_exists($view, 'with')) {
            $view->with('layout', $layout);
        }
    }
}
