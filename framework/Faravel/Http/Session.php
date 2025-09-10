<?php

namespace Faravel\Http;

class Session
{
    protected bool $started = false;

    protected function cycleFlash(): void
    {
        $this->ensureStarted();
        if (!isset($_SESSION['_flash'])) {
            $_SESSION['_flash'] = ['current' => [], 'next' => []];
        }
        $_SESSION['_flash']['current'] = $_SESSION['_flash']['next'];
        $_SESSION['_flash']['next'] = [];
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Настраиваем cookie PHPSESSID ДО старта сессии
            $secure =
                (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

            // PHP 7.3+: массив, иначе — строкой
            if (PHP_VERSION_ID >= 70300) {
                session_set_cookie_params([
                    'lifetime' => 0,
                    'path'     => '/',
                    'domain'   => '',
                    'secure'   => $secure,       // Важно: false в локалке по HTTP
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            } else {
                // совместимость со старыми PHP (если вдруг)
                ini_set('session.cookie_secure', $secure ? '1' : '0');
                ini_set('session.cookie_httponly', '1');
                if (ini_get('session.cookie_samesite') === false) {
                    // у старых версий параметра может не быть — пропускаем
                } else {
                    ini_set('session.cookie_samesite', 'Lax');
                }
            }

            session_start();
            $this->started = true;
            $this->cycleFlash();
        }
    }

    public function all(): array
    {
        $this->ensureStarted();
        return $_SESSION;
    }

    public function get(string $key, $default = null)
    {
        $this->ensureStarted();
        return $_SESSION[$key] ?? $default;
    }

    public function put(string $key, $value): void
    {
        $this->ensureStarted();
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        $this->ensureStarted();
        return isset($_SESSION[$key]);
    }

    public function forget(string $key): void
    {
        $this->ensureStarted();
        unset($_SESSION[$key]);
    }

    public function pull(string $key, $default = null)
    {
        $this->ensureStarted();
        $value = $_SESSION[$key] ?? $default;
        unset($_SESSION[$key]);
        return $value;
    }

    public function flush(): void
    {
        $this->ensureStarted();
        $_SESSION = [];
    }

    protected function ensureStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $this->start();
        }
    }

    public function regenerate(): void
    {
        $this->ensureStarted();
        session_regenerate_id(true);
    }

    public function invalidate(): void
    {
        $this->ensureStarted();
        $_SESSION = [];
        session_destroy();
        session_start();
    }

    public function flash(string $key, mixed $value): void
    {
        $this->ensureStarted();
        if (!isset($_SESSION['_flash'])) {
            $_SESSION['_flash'] = ['current' => [], 'next' => []];
        }
        $_SESSION['_flash']['next'][$key] = $value;
    }

    public function old(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();
        return $_SESSION['_flash']['current'][$key] ?? $default;
    }

    public function clearOldFlash(): void
    {
        $this->ensureStarted();
        if (isset($_SESSION['_flash'])) {
            $_SESSION['_flash']['current'] = [];
        }
    }
}
