<?php
// v0.4.118
// ModOnly — middleware для доступа в раздел модератора.  Пускает только
// авторизованных пользователей с ролью id ≥ 3 (модератор, супер‑модератор,
// разработчик).  Неавторизованные пользователи перенаправляются на страницу
// входа, а пользователи с ролью ниже 3 получают ответ 403.

namespace App\Http\Middleware;

use Closure;
use Faravel\Http\Middleware\MiddlewareInterface;
use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Support\Facades\Auth;

final class ModOnly implements MiddlewareInterface
{
    /**
     * Обработать входящий запрос.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if (!$user) {
            return redirect('/login');
        }
        $roleId = (int)($user['role_id'] ?? 0);
        if ($roleId < 3) {
            return response('Forbidden', 403);
        }
        return $next($request);
    }
}