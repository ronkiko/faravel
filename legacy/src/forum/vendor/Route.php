<?php

namespace Vendor;

class Route
{
    protected static $routes = [];

    public static function get($uri, $action)
    {
        self::$routes['GET'][$uri] = $action;
    }

    public static function resolve($method, $uri)
    {
        return self::$routes[$method][$uri] ?? null;
    }
}
