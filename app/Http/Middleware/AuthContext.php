<?php // v0.4.1
/* app/Http/Middleware/AuthContext.php
Purpose: Инициализирует Gate на время запроса по текущей роли пользователя и,
         при желании, добавляет служебный заголовок. Никаких share() во вью.
FIX: Удалён глобальный share() auth-данных во ViewFactory (дублирование логики).
     Источник истины для вью — LayoutService/VM, не middleware.
*/

namespace App\Http\Middleware;

use Faravel\Http\Middleware\MiddlewareInterface;
use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Support\Facades\Auth;
use App\Support\Gate;

final class AuthContext implements MiddlewareInterface
{
    /**
     * Initialize per-request auth context (role → Gate).
     *
     * Preconditions:
     * - AuthService доступен через фасад Auth.
     * - Gate::init(int $roleId) и Gate::current() корректно зарегистрированы.
     *
     * Side effects:
     * - Инициализация Gate (в памяти запроса).
     * - Установка заголовка X-Auth-Role (только для отладки/трассировки).
     *
     * @param Request                 $request Incoming HTTP request.
     * @param \Closure(Request):Response $next Next middleware/handler.
     * @return Response
     */
    public function handle(Request $request, \Closure $next): Response
    {
        // 1) Current user and its role id (guest → 0)
        /** @var array<string,mixed>|null $u */
        $u = Auth::user();
        $roleId = (int)($u['role_id'] ?? 0);

        // 2) Initialize Gate for this request
        Gate::init($roleId);

        // 3) No view->share(): layout data is provided by LayoutService/VM.
        /** @var Response $response */
        $response = $next($request);

        // Optional debug header
        if ($response instanceof Response) {
            $response->setHeader('X-Auth-Role', (string)$roleId);
        }

        return $response;
    }
}
