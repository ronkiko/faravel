<?php

namespace App\Http\Middleware;

use Closure;
use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Http\Middleware\MiddlewareInterface;
use Faravel\Support\Facades\Auth;

class RedirectIfAuthenticated implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response
    {
        // Если уже есть активный пользователь — уводим на дашборд
        if (Auth::user()) {
            return redirect('/dashboard');
        }
        return $next($request);
    }
}
