<?php

namespace Faravel\Http;

class Cookie {
    public static function get($key, $default = null) { return $_COOKIE[$key] ?? $default; }

    public static function make($key, $value, $minutes = 60, $path = '/', $httpOnly = true) {
        $expire = time() + ($minutes * 60);
        setcookie($key, $value, $expire, $path, '', false, $httpOnly);
    }

    public static function forget($key) {
        setcookie($key, '', time() - 3600, '/');
    }
}
