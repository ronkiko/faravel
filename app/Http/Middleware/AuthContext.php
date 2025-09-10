<?php

namespace App\Http\Middleware;

use Faravel\Http\Middleware\MiddlewareInterface;
use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Support\Facades\Auth;
use App\Support\Gate;

/**
 * Прокладывает в запрос контекст авторизации:
 *  - вычисляет текущую роль (roles.id из БД: -1..7),
 *  - инициализирует Gate на этот запрос,
 *  - шарит auth-данные во вьюхи (если ViewFactory поддерживает share()).
 */
final class AuthContext implements MiddlewareInterface
{
    /**
     * @param \Closure(Request): Response $next
     */
    public function handle(Request $request, \Closure $next): Response
    {
        // 1) Текущий пользователь и его роль (точно как в БД: -1..7; гость => 0)
        $u = Auth::user();
        $roleId = (int)($u['role_id'] ?? 0);

        // 2) Инициализируем Gate на время этого запроса
        Gate::init($roleId);

        // 3) Пробуем расшарить данные во вьюхи
        //    (предусмотрена мягкая деградация, если метода share() нет)
        try {
            $view = \app('view'); // Faravel\View\ViewFactory
            if (is_object($view) && method_exists($view, 'share')) {
                $view->share([
                    'authUser' => $u,
                    'authRole' => $roleId,
                    'gate'     => Gate::current(),
                ]);
            }
        } catch (\Throwable $e) {
            // не мешаем запросу, если шаринг не удался
        }

        /** @var Response $response */
        $response = $next($request);

        // (Необязательно) полезно для отладки — видеть роль в ответе
        if ($response instanceof Response) {
            $response->setHeader('X-Auth-Role', (string)$roleId);
        }

        return $response;
    }
}
