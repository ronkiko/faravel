<?php // v0.4.4
/* app/Http/Middleware/SessionMiddleware.php
Purpose: Инициализирует сессию и прокидывает её в Request на КАЖДЫЙ запрос.
FIX: Удалена диагностическая запись; унифицирован запуск сессии (start()) и очистка flash.
*/
namespace App\Http\Middleware;

use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Http\Session;
use Faravel\Http\Middleware\MiddlewareInterface;

final class SessionMiddleware implements MiddlewareInterface
{
    /**
     * Ensure session started per request and attached to Request object.
     *
     * @param \Faravel\Http\Request $request
     * @param \Closure              $next
     * @return \Faravel\Http\Response
     */
    public function handle(Request $request, \Closure $next): Response
    {
        /** @var Session $session */
        $session = container()->make(Session::class);
        $session->start();                // start session for THIS request
        $request->setSession($session);   // attach into Request

        /** @var Response $response */
        $response = $next($request);

        // Drop previous-request flash
        if (method_exists($session, 'clearOldFlash')) {
            $session->clearOldFlash();
        }

        return $response;
    }
}
