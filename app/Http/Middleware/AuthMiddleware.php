<?php

namespace App\Http\Middleware;

use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Support\Facades\Auth;
use Faravel\Http\Middleware\MiddlewareInterface;

/**
 * Middleware, который проверяет, что пользователь авторизован. В противном
 * случае перенаправляет на страницу входа. Его можно назначить маршруту
 * с помощью метода middleware() на RouteDefinition.
 */
class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, \Closure $next): Response
    {
        if (!Auth::check()) {
            // Сохраняем текущий URL, чтобы перенаправить обратно после входа
            $request->session()->flash('redirect_to', $request->path());
            return redirect('/login');
        }
        return $next($request);
    }
}