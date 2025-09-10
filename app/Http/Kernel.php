<?php // v0.4.2
/* app/Http/Kernel.php
Purpose: HTTP kernel that builds the middleware pipeline and dispatches requests to the
         router. The middleware list is taken strictly from the container, published by
         the HttpMiddlewareServiceProvider (single source of truth).
FIX: Removed any config reads and all fallbacks. Kernel now requires that the provider
     publishes 'http.middleware.global' and throws descriptive errors if missing/invalid.
*/

namespace App\Http;

use Faravel\Foundation\Application;
use Faravel\Http\Request;
use Faravel\Http\Response;
use Faravel\Http\Middleware\MiddlewareInterface;
use Faravel\Routing\Router;
use Faravel\Exceptions\MethodNotAllowedException;

final class Kernel
{
    /** @var array<int, class-string<MiddlewareInterface>> Middleware classes for the pipeline. */
    protected array $middleware = [];

    protected Application $app;

    /**
     * Construct kernel and pull middleware list from the container only.
     *
     * Preconditions:
     *  - HttpMiddlewareServiceProvider must publish 'http.middleware.global' as
     *    array<int, class-string<MiddlewareInterface>> before Kernel is constructed.
     * Side effects: none.
     *
     * @param Application $app Application/DI container.
     *
     * @throws \RuntimeException If the container key is missing, invalid, or empty.
     */
    public function __construct(Application $app)
    {
        $this->app = $app;

        try {
            /** @var mixed $list */
            $list = $this->app->make('http.middleware.global');
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "Container key 'http.middleware.global' was not published. ".
                "Ensure HttpMiddlewareServiceProvider registers and publishes the global ".
                "middleware list before Kernel is constructed.",
                previous: $e
            );
        }

        if (!is_array($list)) {
            throw new \RuntimeException(
                "Container key 'http.middleware.global' must be an array of class names."
            );
        }

        $sanitized = $this->sanitizeClassList($list);

        if ($sanitized === []) {
            throw new \RuntimeException(
                "'http.middleware.global' must contain at least one middleware class."
            );
        }

        $this->middleware = $sanitized;
    }

    /**
     * Handle the incoming HTTP request by wrapping the router with the middleware stack.
     *
     * Preconditions: $this->middleware is a non-empty list of class-string<MiddlewareInterface>.
     * Side effects: delegates to router; errors are handled by the exception handler.
     *
     * @return Response
     */
    public function handle(): Response
    {
        try {
            /** @var Request $request */
            $request = $this->app->make(Request::class);
            /** @var Router $router */
            $router  = $this->app->make(Router::class);

            /** @var \Closure(Request): Response $pipeline */
            $pipeline = $this->buildMiddlewareStack(
                /**
                 * Core router delegate.
                 *
                 * @param Request $request
                 * @return Response
                 */
                function (Request $request) use ($router): Response {
                    $response = $router->dispatch($request);

                    if (!$response instanceof Response) {
                        return (new Response())
                            ->status(500)
                            ->setContent('Router must return a Response instance');
                    }
                    return $response;
                }
            );

            $response = $pipeline($request);

            if (!$response instanceof Response) {
                throw new \RuntimeException(
                    'Middleware chain did not return a valid Response.'
                );
            }

            return $response;
        } catch (MethodNotAllowedException $e) {
            return (new Response('Method Not Allowed', 405));
        } catch (\Throwable $e) {
            try {
                /** @var \App\Exceptions\Handler $handler */
                $handler = $this->app->make(\App\Exceptions\Handler::class);
                return $handler->handle($e);
            } catch (\Throwable $inner) {
                return new Response('Internal Server Error', 500);
            }
        }
    }

    /**
     * Wrap the router with the configured middleware chain.
     *
     * @param \Closure(Request): Response $core Core handler (router).
     *
     * @return \Closure(Request): Response
     */
    protected function buildMiddlewareStack(\Closure $core): \Closure
    {
        foreach (array_reverse($this->middleware) as $middlewareClass) {
            $core = function (Request $request) use ($middlewareClass, $core): Response {
                /** @var MiddlewareInterface $middleware */
                $middleware = $this->app->make($middlewareClass);

                if (!$middleware instanceof MiddlewareInterface) {
                    return (new Response())->status(500)->setContent(
                        "Middleware {$middlewareClass} must implement MiddlewareInterface"
                    );
                }

                return $middleware->handle($request, $core);
            };
        }

        return $core;
    }

    /**
     * Normalize list to unique array of class-string values.
     *
     * @param array<mixed> $list
     *
     * @return array<int, class-string<MiddlewareInterface>>
     */
    private function sanitizeClassList(array $list): array
    {
        $out = [];
        foreach ($list as $v) {
            if (is_string($v)) {
                $out[] = $v;
            }
        }
        return array_values(array_unique($out));
    }
}
