<?php // v0.4.2
/*
framework/Http/Request.php
Purpose: Core HTTP request for Faravel: normalizes GET/POST/cookies/headers and exposes helpers
         for controllers and router; provides a Laravel-like API surface.
FIX: Added Laravel-style query(?string $key = null, mixed $default = null) for GET params and
     aligned docblocks/typing.
*/
namespace Faravel\Http;

use Faravel\Exceptions\MethodNotAllowedException;
use Faravel\Http\UploadedFile;
use Faravel\Http\Session;

class Request
{
    /** @var array<string,mixed> */
    protected array $get;
    /** @var array<string,mixed> */
    protected array $post;
    /** @var array<string,mixed> */
    protected array $cookies;
    /** @var array<string,string> */
    protected array $headers;
    protected string $method;
    protected string $uri;
    protected Session $session;

    /**
     * Allowed HTTP methods. By default only GET and POST.
     * Extra methods can be enabled via setAllowedMethods()/allowMethod().
     *
     * @var array<int,string>
     */
    protected static array $allowedMethods = ['GET', 'POST'];

    public function __construct()
    {
        $this->get     = $_GET;
        $this->post    = $_POST;
        $this->cookies = $_COOKIE;
        $this->headers = $this->loadHeaders();
        $this->method  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->uri     = $this->parseUri();

        // Validate method early to keep Router simple.
        if (!in_array($this->method, self::$allowedMethods, true)) {
            throw new MethodNotAllowedException("HTTP-метод {$this->method} не разрешён.");
        }
    }

    /**
     * Override the full list of allowed HTTP methods.
     *
     * @param array<int,string> $methods
     * @return void
     */
    public static function setAllowedMethods(array $methods): void
    {
        self::$allowedMethods = array_map('strtoupper', $methods);
    }

    /**
     * Allow a single HTTP method in addition to current set.
     *
     * @param string $method
     * @return void
     */
    public static function allowMethod(string $method): void
    {
        $upper = strtoupper($method);
        if (!in_array($upper, self::$allowedMethods, true)) {
            self::$allowedMethods[] = $upper;
        }
    }

    /**
     * Load headers from the environment.
     *
     * @return array<string,string>
     */
    protected function loadHeaders(): array
    {
        if (function_exists('getallheaders')) {
            /** @var array<string,string> $h */
            $h = getallheaders();
            return $h;
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $name = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[ucwords($name, '-')] = (string)$value;
            }
        }
        return $headers;
    }

    /**
     * Parse and normalize request URI to a clean path (no trailing slash).
     *
     * @return string
     */
    protected function parseUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        return rtrim((string)parse_url($uri, PHP_URL_PATH), '/') ?: '/';
    }

    // Router helper
    public function path(): string
    {
        return $this->uri;
    }

    public function method(): string
    {
        return $this->method;
    }

    /**
     * Laravel-like accessor for QUERY_STRING (GET only).
     * If $key is null, returns the whole query array.
     *
     * @param ?string $key     Key or null to get all query params.
     * @param mixed   $default Default value when key is missing.
     * @pre No preconditions; safe to call without session.
     * @side-effects None.
     * @return array<string,mixed>|string|int|float|bool|null
     * @example $page = (int)$req->query('page', 1);
     */
    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->get;
        }
        return $this->get[$key] ?? $default;
    }

    /**
     * GET bag accessor (alias to query()).
     *
     * @param ?string $key
     * @param mixed   $default
     * @return array<string,mixed>|string|int|float|bool|null
     */
    public function get(?string $key = null, mixed $default = null): mixed
    {
        return $key !== null ? ($this->get[$key] ?? $default) : $this->get;
    }

    /**
     * POST bag accessor.
     *
     * @param ?string $key
     * @param mixed   $default
     * @return array<string,mixed>|string|int|float|bool|null
     */
    public function post(?string $key = null, mixed $default = null): mixed
    {
        return $key !== null ? ($this->post[$key] ?? $default) : $this->post;
    }

    /**
     * Unified accessor for input (GET + POST).
     *
     * @param ?string $key
     * @param mixed   $default
     * @return array<string,mixed>|string|int|float|bool|null
     */
    public function input(?string $key = null, mixed $default = null): mixed
    {
        $data = array_merge($this->get, $this->post);
        return $key !== null ? ($data[$key] ?? $default) : $data;
    }

    /**
     * Shortcut for full input array (GET + POST).
     *
     * @return array<string,mixed>
     */
    public function all(): array
    {
        /** @var array<string,mixed> $a */
        $a = $this->input();
        return $a;
    }

    /**
     * Cookie bag accessor.
     *
     * @param ?string $key
     * @param mixed   $default
     * @return array<string,mixed>|string|int|float|bool|null
     */
    public function cookie(?string $key = null, mixed $default = null): mixed
    {
        return $key !== null ? ($this->cookies[$key] ?? $default) : $this->cookies;
    }

    /**
     * Header accessor. If $key is null, returns all headers.
     *
     * @param ?string $key
     * @param mixed   $default
     * @return array<string,string>|string|null
     */
    public function header(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->headers;
        }
        $key = ucwords(strtolower($key), '-');
        return $this->headers[$key] ?? $default;
    }

    /**
     * Back-compat alias for code expecting RequestHeadersGet().
     * Behaves like header(): returns one header or all headers when $key is null.
     *
     * @param ?string $key
     * @param mixed   $default
     * @return array<string,string>|string|null
     */
    public function RequestHeadersGet(?string $key = null, mixed $default = null): mixed
    {
        return $this->header($key, $default);
    }

    /**
     * Access to $_SERVER entry.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function server(string $key, mixed $default = null): mixed
    {
        return $_SERVER[$key] ?? $default;
    }

    public function isGet(): bool    { return $this->method === 'GET'; }
    public function isPost(): bool   { return $this->method === 'POST'; }
    public function isPut(): bool    { return $this->method === 'PUT'; }
    public function isDelete(): bool { return $this->method === 'DELETE'; }
    public function isPatch(): bool  { return $this->method === 'PATCH'; }
    public function isOptions(): bool{ return $this->method === 'OPTIONS'; }
    public function isHead(): bool   { return $this->method === 'HEAD'; }

    /**
     * Pick only the given keys from full input (GET + POST).
     *
     * @param array<int,string> $keys
     * @return array<string,mixed>
     */
    public function only(array $keys): array
    {
        $data = $this->input();
        $result = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $result[$key] = $data[$key];
            }
        }
        return $result;
    }

    /**
     * Return all input except the given keys.
     *
     * @param array<int,string> $keys
     * @return array<string,mixed>
     */
    public function except(array $keys): array
    {
        $data = $this->input();
        foreach ($keys as $key) {
            unset($data[$key]);
        }
        return $data;
    }

    /**
     * Read value from previous-request flash session.
     *
     * @param string $key     Non-empty key name.
     * @param mixed  $default Default when key is missing.
     * @return mixed
     */
    public function old(string $key, mixed $default = null): mixed
    {
        return $this->session->old($key, $default);
    }

    /**
     * Inject session instance (wired in Kernel).
     *
     * @param \Faravel\Http\Session $session
     * @return void
     */
    public function setSession(Session $session): void
    {
        $this->session = $session;
    }

    /**
     * Get session from container (lazy).
     *
     * @return \Faravel\Http\Session
     */
    public function session(): Session
    {
        if (!isset($this->session)) {
            $this->session = container()->make(Session::class);
        }
        return $this->session;
    }

    /**
     * Get uploaded file by key (or null if not uploaded).
     *
     * @param string $key
     * @return \Faravel\Http\UploadedFile|null
     */
    public function file(string $key): ?UploadedFile
    {
        if (!isset($_FILES[$key])) {
            return null;
        }
        return new UploadedFile($_FILES[$key]);
    }
}
