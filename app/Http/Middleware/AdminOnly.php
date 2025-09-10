<?php
// v0.1.1
// AdminOnly — middleware для доступа в админку. Пускает только авторизованных пользователей,
// для которых AdminVisibilityPolicy::canAccessAdmin(role_id) возвращает true.
// FIX: сигнатура handle(...) приведена к интерфейсу (Closure $next), добавлен use Closure.

namespace App\Http\Middleware;

use Closure;
use Faravel\Http\Middleware\MiddlewareInterface;
use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Support\Facades\Auth;
use App\Services\Auth\AdminVisibilityPolicy;

class AdminOnly implements MiddlewareInterface
{
    private AdminVisibilityPolicy $policy;

    public function __construct(?AdminVisibilityPolicy $policy = null)
    {
        // Ленивый резолв: не требуем DI-контейнера.
        $this->policy = $policy ?? new AdminVisibilityPolicy();
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if (!$user) {
            return redirect('/login');
        }

        $roleId = (int)($user['role_id'] ?? 0);
        if (!$this->policy->canAccessAdmin($roleId)) {
            return response('Forbidden', 403);
        }

        return $next($request);
    }
}
