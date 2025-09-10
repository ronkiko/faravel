<?php // v0.4.2
/* framework/Faravel/Routing/Router.php
Назначение: регистратор и диспетчер маршрутов Faravel; строит стек route-middleware
и выполняет action контроллера.
FIX: извлечение маршрутных middleware через единый контейнер приложения
(container()) вместо legacy app()/Container; шапка и PHPDoc приведены к формату.
*/

namespace Faravel\Routing;

use Exception;
use Faravel\Http\Request;
use Faravel\Http\Response;

class Router
{
    /**
     * Все зарегистрированные маршруты.
     *
     * @var array<string, array<int, RouteDefinition>>
     */
    protected static array $routes = [];

    /**
     * Стеки групп маршрутов. Используется для наследования префиксов и middleware.
     *
     * @var array<int, array{prefix: string, middleware: array<int, class-string>}>
     */
    protected static array $groupStack = [];

    /**
     * Реестр именованных маршрутов.
     *
     * @var array<string, RouteDefinition>
     */
    protected static array $routesByName = [];

    /**
     * Получить все зарегистрированные маршруты.
     * Ключ — HTTP-метод, значение — список RouteDefinition для этого метода.
     *
     * @return array<string, array<int, RouteDefinition>>
     */
    public static function getRoutes(): array
    {
        return self::$routes;
    }

    /**
     * Зарегистрировать маршрут GET.
     *
     * @param string $uri
     * @param mixed  $action
     * @return RouteDefinition
     */
    public static function get(string $uri, $action): RouteDefinition
    {
        return self::addRoute('GET', $uri, $action);
    }

    /**
     * Зарегистрировать маршрут POST.
     *
     * @param string $uri
     * @param mixed  $action
     * @return RouteDefinition
     */
    public static function post(string $uri, $action): RouteDefinition
    {
        return self::addRoute('POST', $uri, $action);
    }

    /**
     * Зарегистрировать маршрут PUT.
     *
     * @param string $uri
     * @param mixed  $action
     * @return RouteDefinition
     */
    public static function put(string $uri, $action): RouteDefinition
    {
        return self::addRoute('PUT', $uri, $action);
    }

    /**
     * Зарегистрировать маршрут DELETE.
     *
     * @param string $uri
     * @param mixed  $action
     * @return RouteDefinition
     */
    public static function delete(string $uri, $action): RouteDefinition
    {
        return self::addRoute('DELETE', $uri, $action);
    }

    /**
     * Зарегистрировать маршрут PATCH.
     *
     * @param string $uri
     * @param mixed  $action
     * @return RouteDefinition
     */
    public static function patch(string $uri, $action): RouteDefinition
    {
        return self::addRoute('PATCH', $uri, $action);
    }

    /**
     * Базовая логика добавления маршрута (общая для всех HTTP-методов).
     *
     * @param string $method
     * @param string $uri
     * @param mixed  $action
     * @return RouteDefinition
     */
    protected static function addRoute(string $method, string $uri, $action): RouteDefinition
    {
        $prefix = '';
        $groupMiddleware = [];

        foreach (self::$groupStack as $group) {
            if (!empty($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
            if (!empty($group['middleware'])) {
                $groupMiddleware = array_merge($groupMiddleware, $group['middleware']);
            }
        }

        $fullUri = rtrim($prefix . '/' . ltrim($uri, '/'), '/') ?: '/';
        $routeDef = new RouteDefinition($method, $fullUri, $action);

        // Наследуем групповые middleware
        $routeDef->addMiddleware($groupMiddleware);

        self::$routes[$method][] = $routeDef;
        return $routeDef;
    }

    /**
     * Создать группу маршрутов с общим префиксом или middleware.
     *
     * @param array<string,mixed> $attributes
     * @param callable            $callback
     * @return void
     */
    public static function group(array $attributes, callable $callback): void
    {
        $prefix = $attributes['prefix'] ?? '';
        $middleware = isset($attributes['middleware'])
            ? (array) $attributes['middleware']
            : [];

        self::$groupStack[] = [
            'prefix'     => $prefix,
            'middleware' => $middleware,
        ];

        $callback();

        array_pop(self::$groupStack);
    }

    /**
     * Зарегистрировать именованный маршрут (например, для генерации ссылок).
     *
     * @param string          $name
     * @param RouteDefinition $route
     * @return void
     */
    public static function registerNamedRoute(string $name, RouteDefinition $route): void
    {
        self::$routesByName[$name] = $route;
    }

    /**
     * Получить URI именованного маршрута, подставив параметры.
     *
     * @param string                         $name
     * @param array<string,string|int>       $params
     * @return string
     *
     * @throws Exception Если имя не зарегистрировано.
     */
    public static function route(string $name, array $params = []): string
    {
        if (!isset(self::$routesByName[$name])) {
            throw new Exception("Route name {$name} is not defined");
        }

        $uri = self::$routesByName[$name]->uri;

        foreach ($params as $key => $value) {
            $uri = str_replace('{' . $key . '}', (string) $value, $uri);
        }

        return $uri;
    }

    /**
     * Найти и выполнить соответствующий маршрут.
     *
     * @param Request $request
     * @return Response
     */
    public static function dispatch(Request $request): Response
    {
        $method = $request->method();
        $uri = rtrim($request->path(), '/') ?: '/';

        if (!isset(self::$routes[$method])) {
            return self::notFound();
        }

        foreach (self::$routes[$method] as $routeDef) {
            $pattern = preg_replace('#\{([^/]+)\}#', '(?P<$1>[^/]+)', $routeDef->uri);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $uri, $matches)) {
                $params = [];
                foreach ($matches as $key => $value) {
                    if (!is_int($key)) {
                        $params[$key] = $value;
                    }
                }

                return self::runRoute($routeDef, $request, $params);
            }
        }

        return self::notFound();
    }

    /**
     * Запустить маршрут со всем связанным middleware и обработчиком.
     *
     * @param RouteDefinition               $routeDef
     * @param Request                       $request
     * @param array<string,string>          $params
     * @return Response
     */
    protected static function runRoute(
        RouteDefinition $routeDef,
        Request $request,
        array $params
    ): Response {
        $core = function (Request $req) use ($routeDef, $params): Response {
            return self::executeAction($routeDef->action, $req, $params);
        };

        foreach (array_reverse($routeDef->middleware) as $middlewareClass) {
            $core = function (Request $req) use ($middlewareClass, $core): Response {
                // ЕДИНЫЙ контейнер приложения
                $instance = \container()->make($middlewareClass);

                if (!$instance instanceof \Faravel\Http\Middleware\MiddlewareInterface) {
                    return new Response('Invalid middleware: ' . $middlewareClass, 500);
                }
                return $instance->handle($req, $core);
            };
        }

        return $core($request);
    }

    /**
     * Выполняет действие маршрута (контроллер или замыкание).
     *
     * @param mixed                        $action
     * @param Request                      $request
     * @param array<string,string>         $params
     * @return Response
     *
     * @throws Exception Если некорректный тип обработчика.
     */
    protected static function executeAction(
        $action,
        Request $request,
        array $params
    ): Response {
        if (is_array($action)) {
            [$controllerClass, $methodName] = $action;

            if (!class_exists($controllerClass)) {
                throw new Exception("Controller class '{$controllerClass}' not found.");
            }

            $controller = new $controllerClass();

            if (!method_exists($controller, $methodName)) {
                throw new Exception(
                    "Method '{$methodName}' not defined in controller '{$controllerClass}'."
                );
            }

            $response = $controller->$methodName($request, $params);
        } elseif (is_callable($action)) {
            $response = $action($request, $params);
        } else {
            throw new Exception('Invalid route action type.');
        }

        return $response instanceof Response
            ? $response
            : new Response((string) $response);
    }

    /**
     * Ответ 404.
     *
     * @return Response
     */
    protected static function notFound(): Response
    {
        return new Response('404 Not Found', 404);
    }

    /**
     * Ответ 405.
     *
     * @return Response
     */
    protected static function methodNotAllowed(): Response
    {
        return new Response('405 Method Not Allowed', 405);
    }
}
