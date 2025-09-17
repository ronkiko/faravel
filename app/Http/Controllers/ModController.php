<?php
// v0.4.118
// ModController — контроллер стартовой страницы раздела модератора.  В
// текущей версии представляет собой заглушку: отображает простое
// приветственное сообщение и сообщает, что функциональность модерации
// будет добавлена позже.  Доступ к этому контроллеру ограничен
// middleware ModOnly (роль id ≥ 3).

namespace App\Http\Controllers;

use Faravel\Http\Request;
use Faravel\Http\Response;
use App\Services\Layout\LayoutService;

final class ModController
{
    /**
     * GET /mod — страница панели модератора (заглушка).
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $layoutService = new LayoutService();
        $layoutVM = $layoutService->build($request, [
            'title'      => 'Модератор',
            'nav_active' => 'mod',
        ]);
        return response()->view('mod.index', [
            'layout' => $layoutVM->toArray(),
        ]);
    }
}