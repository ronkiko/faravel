<?php

namespace App\Http\Middleware;

use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Http\Middleware\MiddlewareInterface;
use Closure;

/**
 * Middleware, проверяющий CSRF‑токен для небезопасных HTTP‑методов (POST, PUT, DELETE).
 */
class VerifyCsrfToken implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response
    {
        // Проверяем токен только для небезопасных методов
        $method = strtoupper($request->method());
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            $sessionToken = $_SESSION['_token'] ?? null;
            $inputToken = $request->input('_token');
            if (!$sessionToken || !$inputToken || !hash_equals($sessionToken, $inputToken)) {
                return (new Response())
                    ->status(419)
                    ->setContent('CSRF token mismatch');
            }
        }
        return $next($request);
    }
}