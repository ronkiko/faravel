<?php # exampleMiddleware

namespace App\Http\Middleware;

use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Http\Middleware\MiddlewareInterface;
use Closure;

class ExampleMiddleware implements MiddlewareInterface
{
    /**
     * Обработка запроса через middleware.
     *
     * @param \Faravel\Http\Request $request
     * @param \Closure $next
     * @return \Faravel\Http\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->server('REMOTE_ADDR');

        // Разрешаем доступ только для IP из белого списка.
        // IP‑адреса могут быть вынесены в конфигурацию; пока список хардкодирован.
        $allowedIps = [
            '172.24.0.1', // desktop
            '10.128.39.19', // cellphone
        ];
        if (in_array($ip, $allowedIps, true)) {
            return $next($request);
        }

        $response = new Response();
        return $response
            ->status(403)
            ->setContent("Доступ запрещён с IP: $ip");
    }
}
