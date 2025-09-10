<?php // Faravel/Support/Controller.php
namespace Faravel;

abstract class Controller
{
    protected array $middleware = [];

    public function middleware(array|string $middleware, array $only = [])
    {
        foreach ((array) $middleware as $item) {
            $this->middleware[] = ['middleware' => $item, 'only' => $only];
        }
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }
}
