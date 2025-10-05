<?php // v0.4.8
/* framework/Faravel/Routing/Router.php
Назначение: регистратор и диспетчер маршрутов Faravel; строит стек route-middleware
и выполняет action контроллера.
FIX: Добавлен лог ROUTE.RESOLVE перед созданием контроллера через контейнер и
     логирование исключений при вызове метода экшена (ROUTE.ACTION). При неудаче
     make() — лог и фолбэк на buildViaReflection().
*/

namespace Faravel\Routing;

use Exception;
use Faravel\Http\Request;
use Faravel\Http\Response;
use ReflectionClass;
use ReflectionNamedType;
use App\Support\Logger;

class Router
{
    /**
     * Все зарегистрированные маршруты.
     *
     * @var array<string, array<int, RouteDefinition>>
     */
    protected static array $routes = [];

    /**
     * Стеки групп маршрутов. Наследование префиксов и middleware.
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
     *
     * @return array<string, array<int, RouteDefinition>>
     */
    public static function getRoutes(): array
    {
        return self::$routes;
    }

    /** @return RouteDefinition */
    public static function get(string $uri, $action): RouteDefinition
    {
        return self::addRoute('GET', $uri, $action);
    }

    /** @return RouteDefinition */
    public static function post(string $uri, $action): RouteDefinition
    {
        return self::addRoute('POST', $uri, $action);
    }

    /** @return RouteDefinition */
    public static function put(string $uri, $action): RouteDefinition
    {
        return self::addRoute('PUT', $uri, $action);
    }

    /** @return RouteDefinition */
    public static function delete(string $uri, $action): RouteDefinition
    {
        return self::addRoute('DELETE', $uri, $action);
    }

    /** @return RouteDefinition */
    public static function patch(string $uri, $action): RouteDefinition
    {
        return self::addRoute('PATCH', $uri, $action);
    }

    /**
     * Базовая логика добавления маршрута.
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
     * Группа маршрутов.
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
     * Зарегистрировать именованный маршрут.
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
     * Получить URI именованного маршрута.
     *
     * @param string                   $name
     * @param array<string,int|string> $params
     * @return string
     * @throws Exception
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
     * Найти и выполнить маршрут.
     *
     * @param Request $request
     * @return Response
     */
    public static function dispatch(Request $request): Response
    {
        $method = $request->method();
        $uri    = rtrim($request->path(), '/') ?: '/';

        Logger::log('ROUTER.DISPATCH', $method . ' ' . $uri);

        if (!isset(self::$routes[$method])) {
            Logger::log('ROUTER.NOTFOUND', 'No routes for ' . $method);
            return self::notFound();
        }

        foreach (self::$routes[$method] as $routeDef) {
            $pattern = preg_replace('#\{([^/]+)\}#', '(?P<$1>[^/]+)', $routeDef->uri);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $uri, $matches)) {
                Logger::log('ROUTE.MATCH', $method . ' ' . $routeDef->uri);
                $params = [];
                foreach ($matches as $key => $value) {
                    if (!is_int($key)) {
                        $params[$key] = $value;
                    }
                }

                return self::runRoute($routeDef, $request, $params);
            }
        }

        Logger::log('ROUTER.NOTFOUND', 'No match for ' . $method . ' ' . $uri);
        return self::notFound();
    }

    /**
     * Запустить маршрут со стеком middleware.
     *
     * @param RouteDefinition              $routeDef
     * @param Request                      $request
     * @param array<string,string>         $params
     * @return Response
     */
    protected static function runRoute(
        RouteDefinition $routeDef,
        Request $request,
        array $params
    ): Response {
        $core = function (Request $req) use ($routeDef, $params): Response {
            Logger::log('ROUTE.RUN', 'Executing route action');
            return self::executeAction($routeDef->action, $req, $params);
        };

        foreach (array_reverse($routeDef->middleware) as $middlewareClass) {
            $core = function (Request $req) use ($middlewareClass, $core): Response {
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
     * Выполнить действие маршрута (контроллер или замыкание).
     *
     * @param mixed                        $action
     * @param Request                      $request
     * @param array<string,string>         $params
     * @return Response
     *
     * @throws Exception
     */
    protected static function executeAction(
        $action,
        Request $request,
        array $params
    ): Response {
        // Распаковываем параметры маршрута в порядке объявления.
        $args = array_values($params);

        if (is_array($action)) {
            [$controllerClass, $methodName] = $action;

            if (!class_exists($controllerClass)) {
                throw new Exception("Controller class '{$controllerClass}' not found.");
            }

            // Новый явный лог перед резолвом контроллера.
            Logger::log('ROUTE.RESOLVE', $controllerClass . '@' . $methodName);

            // 1) Контроллер через контейнер, при неудаче — через рефлексию.
            try {
                $controller = \container()->make($controllerClass);
            } catch (\Throwable $e) {
                Logger::exception('ROUTE.RESOLVE', $e, ['class' => $controllerClass]);
                $controller = self::buildViaReflection($controllerClass);
            }

            // 2) Разрешение метода: заданный → алиас Form → __invoke() → ошибка.
            $callableMethod = null;
            if (method_exists($controller, $methodName)) {
                $callableMethod = $methodName;
            } else {
                // Алиас: loginForm|registerForm → showLoginForm|showRegisterForm
                if (str_ends_with($methodName, 'Form')) {
                    $base = substr($methodName, 0, -4); // cut 'Form'
                    $alt  = 'show' . ucfirst($base) . 'Form';
                    if (method_exists($controller, $alt)) {
                        Logger::log(
                            'ACTION.METHOD.ALIAS',
                            $controllerClass . " '{$methodName}'→'{$alt}'"
                        );
                        $callableMethod = $alt;
                    }
                }
                // Фолбэк: __invoke
                if ($callableMethod === null && method_exists($controller, '__invoke')) {
                    Logger::log(
                        'ACTION.METHOD.FALLBACK',
                        $controllerClass . " missing '{$methodName}', using __invoke()"
                    );
                    $callableMethod = '__invoke';
                }
            }

            if ($callableMethod === null) {
                $public = array_values(array_filter(
                    get_class_methods($controller),
                    static fn($m) => $m !== '__construct'
                ));
                $hint = $public ? (' Available: ' . implode(',', $public)) : '';
                throw new Exception(
                    "Method '{$methodName}' not defined in controller '{$controllerClass}'." . $hint
                );
            }

            Logger::log('ACTION.CONTROLLER', $controllerClass . '@' . $callableMethod);

            try {
                /** @var mixed $response */
                $response = $controller->{$callableMethod}($request, ...$args);
            } catch (\Throwable $e) {
                Logger::exception(
                    'ROUTE.ACTION',
                    $e,
                    ['class' => $controllerClass, 'method' => $callableMethod]
                );
                throw $e;
            }
        } elseif (is_callable($action)) {
            Logger::log('ACTION.CLOSURE', 'Invoking route closure');
            try {
                /** @var mixed $response */
                $response = $action($request, ...$args);
            } catch (\Throwable $e) {
                Logger::exception('ROUTE.ACTION', $e, ['closure' => true]);
                throw $e;
            }
        } else {
            throw new Exception('Invalid route action type.');
        }

        return $response instanceof Response
            ? $response
            : new Response((string) $response);
    }

    /**
     * Построить объект класса через рефлексию, автоподставляя зависимости.
     *
     * @param class-string $class
     * @return object
     */
    protected static function buildViaReflection(string $class): object
    {
        $ref = new ReflectionClass($class);
        $ctor = $ref->getConstructor();

        if ($ctor === null || $ctor->getNumberOfRequiredParameters() === 0) {
            return new $class();
        }

        $deps = [];
        foreach ($ctor->getParameters() as $p) {
            $t = $p->getType();

            if ($t instanceof ReflectionNamedType && !$t->isBuiltin()) {
                $depClass = $t->getName();

                // Сначала просим контейнер (для интерфейсов/абстракций)
                try {
                    $deps[] = \container()->make($depClass);
                    continue;
                } catch (\Throwable $e) {
                    // Падать нельзя — пробуем собрать конкретный класс.
                }

                if (class_exists($depClass)) {
                    $deps[] = self::buildViaReflection($depClass);
                    continue;
                }

                throw new \RuntimeException(
                    "Unresolvable dependency {$depClass} for {$class}::__construct()"
                );
            }

            if ($p->isDefaultValueAvailable()) {
                $deps[] = $p->getDefaultValue();
                continue;
            }

            throw new \RuntimeException(
                "Cannot autowire scalar parameter \${$p->getName()} in {$class}::__construct()"
            );
        }

        return $ref->newInstanceArgs($deps);
    }

    /** @return Response */
    protected static function notFound(): Response
    {
        return new Response('404 Not Found', 404);
    }

    /** @return Response */
    protected static function methodNotAllowed(): Response
    {
        return new Response('405 Method Not Allowed', 405);
    }
}
