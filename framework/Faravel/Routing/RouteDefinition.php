<?php

namespace Faravel\Routing;

/**
 * Класс определения маршрута. Хранит параметры маршрута, имя и middleware.
 * Позволяет назначать имя и middleware цепочкой вызовов.
 */
class RouteDefinition
{
    /** @var string HTTP‑метод */
    public string $method;

    /** @var string URI с префиксом */
    public string $uri;

    /** @var mixed Действие маршрута (callable или [ControllerClass, method]) */
    public $action;

    /** @var string|null Имя маршрута */
    public ?string $name = null;

    /** @var array<int, class-string> Middleware, назначенные маршруту */
    public array $middleware = [];

    public function __construct(string $method, string $uri, $action)
    {
        $this->method = $method;
        $this->uri = $uri;
        $this->action = $action;
    }

    /**
     * Задать имя маршрута. Регистрирует маршрут в глобальном реестре имен.
     *
     * @param string $name
     * @return static
     */
    public function name(string $name): self
    {
        $this->name = $name;
        Router::registerNamedRoute($name, $this);
        return $this;
    }

    /**
     * Назначить middleware для маршрута. Можно передать строку или массив.
     *
     * @param string|array<int, string> $middleware
     * @return static
     */
    public function middleware($middleware): self
    {
        $list = is_array($middleware) ? $middleware : [$middleware];
        $this->middleware = array_merge($this->middleware, $list);
        return $this;
    }

    /**
     * Добавить middleware (используется для наследования групповых middleware).
     *
     * @param array<int, string> $middleware
     */
    public function addMiddleware(array $middleware): void
    {
        $this->middleware = array_merge($this->middleware, $middleware);
    }
}