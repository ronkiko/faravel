<?php # midlwareInterface

namespace Faravel\Http\Middleware;

use Faravel\Http\Request;
use Faravel\Http\Response;
use Closure;

interface MiddlewareInterface
{
    /**
     * Обработка запроса через middleware.
     *
     * @param \Faravel\Http\Request $request
     * @param \Closure $next
     * @return \Faravel\Http\Response
     */
    public function handle(Request $request, Closure $next): Response;
}
