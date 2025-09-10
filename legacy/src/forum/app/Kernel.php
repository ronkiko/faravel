<?php

namespace App;

use Vendor\Route;

class Kernel
{
    public function handle()
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];

        $action = Route::resolve($method, $uri);

        if (!$action) {
            http_response_code(404);
            return '404 Not Found';
        }

        [$controller, $method] = $action;
        return (new $controller)->$method();
    }
}
