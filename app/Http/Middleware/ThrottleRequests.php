<?php

namespace App\Http\Middleware;

use Closure;
use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Http\Middleware\MiddlewareInterface;
use App\Services\SettingsService;

/**
 * Глобальный троттлинг по ID сессии для всех маршрутов.
 * Ключи настроек (таблица settings):
 *  - throttle.window.sec
 *  - throttle.get.max
 *  - throttle.post.max
 *  - throttle.session.max
 *  - throttle.exempt.paths (CSV: "/__cfg,/__db_ping")
 *
 * По умолчанию (если в settings нет значений):
 *  window=60, GET=120/мин, POST=15/мин, SESSION=300/мин
 */
class ThrottleRequests implements MiddlewareInterface
{
    private const DEF_WINDOW_SEC   = 60;
    private const DEF_GET_MAX      = 120;
    private const DEF_POST_MAX     = 15;
    private const DEF_SESSION_MAX  = 300;

    public function handle(Request $request, Closure $next): Response
    {
        // Гарантируем сессию
        $session = $request->session();
        $session->start();

        // Считываем параметры из settings
        $windowSec  = SettingsService::getInt('throttle.window.sec',   self::DEF_WINDOW_SEC, 1, 3600);
        $getMax     = SettingsService::getInt('throttle.get.max',     self::DEF_GET_MAX,    1, 10000);
        $postMax    = SettingsService::getInt('throttle.post.max',    self::DEF_POST_MAX,   1, 10000);
        $sessionMax = SettingsService::getInt('throttle.session.max', self::DEF_SESSION_MAX,1, 50000);

        $exemptCsv  = (string) SettingsService::get('throttle.exempt.paths', '');
        $exempt     = array_filter(array_map(
            static fn($s) => rtrim(trim($s), '/') ?: '/',
            explode(',', $exemptCsv)
        ));

        $method = strtoupper($request->method()); // Faravel: GET|POST (другие отклоняются в Request)
        $path   = $this->normalizePath(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

        if (in_array($path, $exempt, true)) {
            return $next($request);
        }

        $perRouteLimit = ($method === 'GET') ? $getMax : $postMax;

        $bucketKey  = "sess:{$method}:{$path}";
        $globalKey  = "sess:GLOBAL";
        $now        = time();
        $windowFrom = $now - $windowSec;

        if (!isset($_SESSION['_throttle']) || !is_array($_SESSION['_throttle'])) {
            $_SESSION['_throttle'] = [];
        }
        foreach ([$bucketKey, $globalKey] as $k) {
            if (!isset($_SESSION['_throttle'][$k]) || !is_array($_SESSION['_throttle'][$k])) {
                $_SESSION['_throttle'][$k] = [];
            }
            // чистим старые записи
            $_SESSION['_throttle'][$k] = array_values(array_filter(
                $_SESSION['_throttle'][$k],
                static fn ($ts) => is_int($ts) && $ts >= $windowFrom
            ));
        }

        $routeCount  = count($_SESSION['_throttle'][$bucketKey]);
        $globalCount = count($_SESSION['_throttle'][$globalKey]);

        if ($routeCount >= $perRouteLimit || $globalCount >= $sessionMax) {
            $retryAfter = $this->retryAfter($_SESSION['_throttle'][$bucketKey], $_SESSION['_throttle'][$globalKey], $now, $windowSec);
            return response("Too Many Requests\nTry again in {$retryAfter} seconds.\n", 429)->withHeaders([
                'Retry-After'            => (string) $retryAfter,
                'X-RateLimit-Limit'      => (string) $perRouteLimit,
                'X-RateLimit-Remaining'  => '0',
                'X-RateLimit-Route'      => $bucketKey,
                'X-RateLimit-Global'     => (string) $sessionMax,
            ]);
        }

        // записываем хит
        $_SESSION['_throttle'][$bucketKey][] = $now;
        $_SESSION['_throttle'][$globalKey][] = $now;

        $remaining = max(0, $perRouteLimit - count($_SESSION['_throttle'][$bucketKey]));

        /** @var Response $resp */
        $resp = $next($request);

        // информационные заголовки
        $resetIn = $this->resetIn($_SESSION['_throttle'][$bucketKey], $now, $windowSec);
        return $resp->withHeaders([
            'X-RateLimit-Limit'     => (string) $perRouteLimit,
            'X-RateLimit-Remaining' => (string) $remaining,
            'X-RateLimit-Route'     => $bucketKey,
            'X-RateLimit-Global'    => (string) $sessionMax,
            'X-RateLimit-Reset'     => (string) $resetIn,
        ]);
    }

    private function normalizePath(string $path): string
    {
        if ($path === '') return '/';
        $norm = rtrim($path, '/');
        return $norm === '' ? '/' : $norm;
    }

    private function resetIn(array $bucket, int $now, int $windowSec): int
    {
        if (empty($bucket)) return $windowSec;
        $oldest = min($bucket);
        $delta  = $windowSec - ($now - $oldest);
        return max(1, $delta);
    }

    private function retryAfter(array $routeBucket, array $globalBucket, int $now, int $windowSec): int
    {
        $cands = [];
        if (!empty($routeBucket))  $cands[] = $windowSec - ($now - min($routeBucket));
        if (!empty($globalBucket)) $cands[] = $windowSec - ($now - min($globalBucket));
        $best = 0;
        foreach ($cands as $v) $best = max($best, (int)$v);
        return max(1, $best ?: $windowSec);
    }
}
