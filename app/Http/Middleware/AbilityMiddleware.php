<?php // v0.3.33

namespace App\Http\Middleware;

use Closure;
use Faravel\Http\Request;
use Faravel\Support\Facades\Auth;
use Faravel\Support\Facades\Gate;

class AbilityMiddleware
{
    /**
     * Пример подключения:
     *   ->middleware([AuthMiddleware::class, AdminOnly::class, AbilityMiddleware::class . ':manage-abilities'])
     *
     * @param  Request $request
     * @param  Closure $next
     * @param  string  $ability Имя способности (зарегистрировано в Gate)
     */
    public function handle(Request $request, Closure $next, string $ability)
    {
        $user = Auth::user();

        // Гость → на логин
        if (!$user) {
            $request->session()->flash('error', 'Требуется вход.');
            return redirect('/login', 302);
        }

        // Проверка через Gate
        if (!Gate::allows($ability)) {
            $request->session()->flash('error', 'Недостаточно прав: ' . $ability);

            // Используем добавленный метод RequestHeadersGet (бек-компат).
            $ref = (string)($request->RequestHeadersGet('Referer') ?? '');
            return redirect($ref !== '' ? $ref : '/', 302);
            // Альтернатива:
            // return response('Forbidden', 403);
        }

        return $next($request);
    }
}
