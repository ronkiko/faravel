<?php // v0.4.3
/* framework/helpers.php
Назначение: глобальные хелперы ядра Faravel: доступ к контейнеру (Application),
путям, ответам/видам, переводам, ACL/аватарам и сессии (включая shim).
FIX: унифицированы обращения к контейнеру через глобальный \app(); у функции
request() убран строгий return type (оставлен PHPDoc), чтобы избежать ложных
P1009 в Intelephense; не меняем структуру и состав — всё как в архиве.
*/

use Faravel\Foundation\Application;
use Faravel\Http\Response;
use Faravel\Http\ResponseFactory;
use Faravel\Support\Config;
use App\Support\Avatar as AvatarSupport;

if (!function_exists('request')) {
    /**
     * Вернуть текущий объект запроса.
     *
     * @return \Faravel\Http\Request
     */
    function request()
    {
        // Используем глобальный helper \app() — он есть в проекте (см. ниже).
        /** @var \Faravel\Http\Request $req */
        $req = \app(\Faravel\Http\Request::class);
        return $req;
    }
}

if (!function_exists('response')) {
    /**
     * Return HTTP ResponseFactory or create a Response immediately.
     *
     * Calling without $content returns the factory (canonical: response()->view()).
     * With non-null $content it creates a ready Response.
     *
     * @param ?string               $content  Response body or null to get the factory.
     * @param int                   $status   HTTP status when $content !== null.
     * @param array<string,string>  $headers  Headers when $content !== null.
     * @return \Faravel\Http\ResponseFactory|\Faravel\Http\Response
     * @phpstan-return ($content is null
     *     ? \Faravel\Http\ResponseFactory
     *     : \Faravel\Http\Response)
     * @psalm-return ($content is null
     *     ? \Faravel\Http\ResponseFactory
     *     : \Faravel\Http\Response)
     */
    function response(?string $content = null, int $status = 200, array $headers = [])
    {
        /** @var \Faravel\Http\ResponseFactory $factory */
        static $factory;

        if ($factory === null) {
            /** Lazily resolve from container once */
            $factory = \app(\Faravel\Http\ResponseFactory::class);
        }

        if ($content === null) {
            // Canonical path: use the factory (response()->view(), etc.)
            return $factory;
        }

        // Immediate response creation when content is provided.
        return $factory->make($content, $status, $headers);
    }
}

if (!function_exists('view')) {
    /**
     * Создать View по имени через фабрику представлений.
     *
     * @param string              $name
     * @param array<string,mixed> $data
     * @return mixed
     */
    function view(string $name, array $data = [])
    {
        return \app('view')->make($name, $data);
    }
}

if (!function_exists('config')) {
    /**
     * Получить значение конфигурации по ключу.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    function config(string $key, $default = null)
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('debug')) {
    /**
     * Простой HTML-дамп переменной (для отладки в браузере).
     *
     * @param mixed $data
     * @return void
     */
    function debug($data): void
    {
        ob_start();
        print_r($data);
        $output = ob_get_clean();

        $output = preg_replace('/=>\s+1(\n|\r)/', '=> true$1', $output);
        $output = preg_replace('/=>\s+(\n|\r)/', '=> false$1', $output);

        $output = htmlspecialchars((string)$output, ENT_QUOTES, 'UTF-8');

        echo <<<HTML
<fieldset style="border:1px solid #bbb;background:#f9f9f9;padding:12px;margin:15px 0;">
  <legend style="font-weight:bold;color:#444;padding:0 8px;">Debug</legend>
  <pre style="margin:0;font-family:monospace;color:#222;">$output</pre>
</fieldset>
HTML;
    }
}

/** Аналог Laravel app(): вернуть контейнер или разрешить абстракцию. */
if (!function_exists('app')) {
    /**
     * @param string|null $abstract
     * @param mixed       $default
     * @return mixed
     */
    function app(?string $abstract = null, mixed $default = null): mixed
    {
        /** @var Application $app */
        $app = Application::getInstance();

        if ($abstract === null) {
            return $app;
        }

        try {
            return $app->make($abstract);
        } catch (\Throwable $e) {
            if (func_num_args() >= 2) {
                return $default;
            }
            throw $e;
        }
    }
}

if (!function_exists('redirect')) {
    /** @param string $url @return Response */
    function redirect(string $url): Response
    {
        $response = new Response('', 302);
        $response->setHeader('Location', $url);
        return $response;
    }
}

/** CSRF helpers */
if (!function_exists('csrf_token')) {
    /** @return string */
    function csrf_token(): string
    {
        if (!isset($_SESSION)) {
            @session_start();
        }
        if (empty($_SESSION['_token'])) {
            $_SESSION['_token'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['_token'];
    }
}
if (!function_exists('csrf_field')) {
    /** @return string */
    function csrf_field(): string
    {
        $token = csrf_token();
        $escaped = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="_token" value="' . $escaped . '">';
    }
}

if (!function_exists('urlFor')) {
    /**
     * Построить URL по имени маршрута.
     *
     * @param string                   $name
     * @param array<string,string|int> $params
     * @return string
     */
    function urlFor(string $name, array $params = []): string
    {
        return \Faravel\Routing\Router::route($name, $params);
    }
}
if (!function_exists('route_url')) {
    /**
     * Алиас для urlFor().
     *
     * @param string                   $name
     * @param array<string,string|int> $params
     * @return string
     */
    function route_url(string $name, array $params = []): string
    {
        return urlFor($name, $params);
    }
}

if (!function_exists('env')) {
    /**
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    function env(string $key, $default = null)
    {
        return \Faravel\Support\Env::get($key, $default);
    }
}

if (!function_exists('base_path')) {
    /**
     * Абсолютный путь к корню приложения (с доп. сегментом).
     *
     * @param string|null $path
     * @return string
     */
    function base_path(?string $path = null): string
    {
        $base = Application::getInstance()->basePath() ?? \dirname(__DIR__);
        if ($path === null || $path === '') {
            return $base;
        }
        return rtrim($base, '/\\') . '/' . ltrim($path, '/\\');
    }
}

if (!function_exists('container')) {
    /** @return Application */
    function container(): Application
    {
        return Application::getInstance();
    }
}

if (!function_exists('dump')) {
    /**
     * @param mixed ...$vars
     * @return void
     */
    function dump(...$vars): void
    {
        echo '<pre>';
        foreach ($vars as $var) {
            var_dump($var);
        }
        echo '</pre>';
    }
}

if (!function_exists('str_slug')) {
    /**
     * @param string $value
     * @param string $sep
     * @param int    $maxLen
     * @return string
     */
    function str_slug(string $value, string $sep = '-', int $maxLen = 100): string
    {
        return \App\Support\Slugger::make($value, $sep, $maxLen);
    }
}
if (!function_exists('slugify')) {
    /**
     * @param string $value
     * @param string $sep
     * @param int    $maxLen
     * @return string
     */
    function slugify(string $value, string $sep = '-', int $maxLen = 100): string
    {
        return \App\Support\Slugger::make($value, $sep, $maxLen);
    }
}
if (!function_exists('unique_slug')) {
    /**
     * @param string      $base
     * @param string      $table
     * @param string      $column
     * @param string      $idColumn
     * @param string|null $excludeId
     * @param string      $sep
     * @param int         $maxLen
     * @return string
     */
    function unique_slug(
        string $base,
        string $table,
        string $column = 'slug',
        string $idColumn = 'id',
        ?string $excludeId = null,
        string $sep = '-',
        int $maxLen = 100
    ): string {
        return \App\Support\Slugger::unique(
            $base,
            $table,
            $column,
            $idColumn,
            $excludeId,
            $sep,
            $maxLen
        );
    }
}

if (!function_exists('__')) {
    /**
     * @param string               $key
     * @param array<string,scalar> $repl
     * @return string
     */
    function __(string $key, array $repl = []): string
    {
        /** @var \App\Support\Lang $t */
        $t = \app('lang');
        return $t->trans($key, $repl);
    }
}
if (!function_exists('trans_choice')) {
    /**
     * @param string               $key
     * @param int                  $count
     * @param array<string,scalar> $repl
     * @return string
     */
    function trans_choice(string $key, int $count, array $repl = []): string
    {
        /** @var \App\Support\Lang $t */
        $t = \app('lang');
        return $t->choice($key, $count, $repl);
    }
}

if (!function_exists('locale')) {
    /** @return string */
    function locale(): string
    {
        /** @var \App\Support\Lang $t */
        $t = \app('lang');
        return $t->getLocale();
    }
}

/**
 * Laravel-like locale helpers for clarity and session sync.
 * - app_locale(): get current app locale.
 * - set_app_locale($loc): set app locale and persist it into session key 'ui.locale'.
 */
if (!function_exists('app_locale')) {
    /** @return string */
    function app_locale(): string
    {
        return locale();
    }
}
if (!function_exists('set_app_locale')) {
    /**
     * @param string $loc
     * @return string New locale actually set.
     */
    function set_app_locale(string $loc): string
    {
        $loc = trim($loc) !== '' ? $loc : 'en';

        try {
            /** @var \App\Support\Lang $t */
            $t = \app('lang');
            if (method_exists($t, 'setLocale')) {
                $t->setLocale($loc);
            }
        } catch (\Throwable) {
            // ignore
        }

        try {
            if (class_exists(\Faravel\Support\Facades\Session::class)) {
                \Faravel\Support\Facades\Session::put('ui.locale', $loc);
            } else {
                $s = session();
                if (method_exists($s, 'put')) {
                    $s->put('ui.locale', $loc);
                }
            }
        } catch (\Throwable) {
            // ignore
        }

        return $loc;
    }
}

if (!function_exists('trans')) {
    /**
     * @param string               $key
     * @param array<string,scalar> $repl
     * @return string
     */
    function trans(string $key, array $repl = []): string
    {
        /** @var \App\Support\Lang $t */
        $t = \app('lang');
        return $t->trans($key, $repl);
    }
}
if (!function_exists('t')) {
    /**
     * @param string               $key
     * @param array<string,scalar> $repl
     * @return string
     */
    function t(string $key, array $repl = []): string
    {
        return trans($key, $repl);
    }
}

// === ACL / Gate helpers ===
if (!function_exists('gate')) {
    /** @return \App\Support\Gate */
    function gate(): \App\Support\Gate
    {
        return \App\Support\Gate::current();
    }
}
if (!function_exists('can')) {
    /** @param string $ability @return bool */
    function can(string $ability): bool
    {
        return \App\Support\Gate::current()->allows($ability);
    }
}
if (!function_exists('cannot')) {
    /** @param string $ability @return bool */
    function cannot(string $ability): bool
    {
        return !can($ability);
    }
}
if (!function_exists('can_any')) {
    /** @param array<int,string> $abilities @return bool */
    function can_any(array $abilities): bool
    {
        return \App\Support\Gate::current()->any($abilities);
    }
}
if (!function_exists('can_all')) {
    /** @param array<int,string> $abilities @return bool */
    function can_all(array $abilities): bool
    {
        return \App\Support\Gate::current()->all($abilities);
    }
}
if (!function_exists('role')) {
    /** @return int */
    function role(): int
    {
        return \App\Support\Gate::current()->level();
    }
}
if (!function_exists('visible_for')) {
    /** @param int $minRole @return bool */
    function visible_for(int $minRole): bool
    {
        return role() >= $minRole;
    }
}

if (!function_exists('avatar_url')) {
    /**
     * @param array<string,mixed>|string $userOrId
     * @param int                        $size
     * @param string                     $variant
     * @return string
     */
    function avatar_url(array|string $userOrId, int $size = 96, string $variant = 'square'): string
    {
        $id = is_array($userOrId) ? (string)($userOrId['id'] ?? '') : (string)$userOrId;
        if ($id === '') {
            $id = 'default';
        }

        $size = max(16, min(512, $size));
        $variant = in_array($variant, ['square', 'circle'], true) ? $variant : 'square';

        return "/u/{$id}/avatar/{$variant}/{$size}";
    }
}
if (!function_exists('avatar_alt')) {
    /**
     * @param array<string,mixed> $user
     * @return string
     */
    function avatar_alt(array $user): string
    {
        $u = $user['username'] ?? 'user';
        return "avatar of {$u}";
    }
}
if (!function_exists('avatar_tag')) {
    /**
     * @param mixed               $userOrId
     * @param array<string,mixed> $opts
     * @return string
     */
    function avatar_tag($userOrId, array $opts = []): string
    {
        return AvatarSupport::tag($userOrId, $opts);
    }
}

if (!function_exists('asset_ver')) {
    /** @param string $path @return string */
    function asset_ver(string $path): string
    {
        $docroot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
        $abs = $docroot . $path;
        $v = is_file($abs) ? (string) @filemtime($abs) : '1';
        return $path . '?v=' . $v;
    }
}

// ======== Session helpers (shim) ========
if (!class_exists('__FaravelSessionShim')) {
    /** Minimalistic session shim. */
    class __FaravelSessionShim
    {
        private function boot(): void
        {
            if (!isset($_SESSION)) {
                @session_start();
            }
            if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
                $_SESSION['_flash'] = [];
            }
        }

        public function get(string $key, mixed $default = null): mixed
        {
            $this->boot();
            return $_SESSION[$key] ?? $_SESSION['_flash'][$key] ?? $default;
        }

        public function put(string $key, mixed $value): void
        {
            $this->boot();
            $_SESSION[$key] = $value;
        }

        public function flash(string $key, mixed $value): void
        {
            $this->boot();
            $_SESSION['_flash'][$key] = $value;
        }

        public function pull(string $key, mixed $default = null): mixed
        {
            $this->boot();
            $val = $_SESSION['_flash'][$key] ?? $default;
            unset($_SESSION['_flash'][$key]);
            return $val;
        }

        public function forget(string $key): void
        {
            $this->boot();
            unset($_SESSION[$key], $_SESSION['_flash'][$key]);
        }

        public function has(string $key): bool
        {
            $this->boot();
            return array_key_exists($key, $_SESSION)
                || array_key_exists($key, $_SESSION['_flash']);
        }

        /** @param string|null $key @param mixed $default @return mixed */
        public function old(?string $key = null, mixed $default = null): mixed
        {
            $this->boot();
            $bag = $_SESSION['_old_input']
                ?? $_SESSION['_flash']['_old_input']
                ?? [];

            if ($key === null) {
                return $bag;
            }

            return is_array($bag) && array_key_exists($key, $bag)
                ? $bag[$key]
                : ($this->pull($key, $default));
        }
    }
}

if (!function_exists('session')) {
    /**
     * Получить объект сессии (или shim).
     *
     * @return object
     */
    function session()
    {
        static $inst = null;
        if ($inst) {
            return $inst;
        }

        try {
            // Через \app() — как в остальных хелперах.
            $inst = \app(\Faravel\Http\Session::class);
        } catch (\Throwable) {
            $inst = new __FaravelSessionShim();
        }
        return $inst;
    }
}

if (!function_exists('old')) {
    /**
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    function old(string $key, mixed $default = '')
    {
        $sess = session();
        if (method_exists($sess, 'old')) {
            return $sess->old($key, $default);
        }
        $bag = $sess->get('_old_input', []);
        return is_array($bag) && array_key_exists($key, $bag) ? $bag[$key] : $default;
    }
}

if (!function_exists('uuid')) {
    /** @return string */
    function uuid(): string
    {
        return \App\Services\Support\IdGenerator::uuidv4();
    }
}
