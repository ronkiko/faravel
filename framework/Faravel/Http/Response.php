<?php

namespace Faravel\Http;

class Response
{
    protected int $status = 200;
    protected array $headers = [];
    protected array $cookies = [];
    protected string $content = '';

    protected static array $macros = [];

    public function __construct(string $content = '', int $status = 200, array $headers = [])
    {
        $this->setContent($content);
        $this->status($status);
        $this->withHeaders($headers);
    }

    /**
     * Установка статуса ответа
     */
    public function status(int $code): static
    {
        $this->status = $code;
        return $this;
    }

    /**
     * Установка одного заголовка
     */
    public function setHeader(string $key, string $value): static
    {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * Установка нескольких заголовков
     */
    public function withHeaders(array $headers): static
    {
        foreach ($headers as $key => $value) {
            $this->setHeader($key, $value);
        }
        return $this;
    }

    /**
     * Установка куки
     */
    public function setCookie(string $key, string $value, int $expire = 0, string $path = '/'): static
    {
        // Сохраняем минимальный набор параметров cookie. Для расширенных
        // настроек используйте метод cookie().
        $this->cookies[] = compact('key', 'value', 'expire', 'path');
        return $this;
    }

    /**
     * Установить куки с расширенными параметрами (домен, secure, httpOnly).
     * Если какие‑то параметры не указаны, будут использованы значения по
     * умолчанию.
     */
    public function cookie(string $key, string $value, int $expire = 0, string $path = '/', string $domain = '', bool $secure = false, bool $httponly = false): static
    {
        $this->cookies[] = compact('key', 'value', 'expire', 'path', 'domain', 'secure', 'httponly');
        return $this;
    }

    /**
     * Вернуть представление в качестве ответа. Собирает HTML через
     * фасад View и устанавливает статус и заголовки.
     */
    public function view(string $viewName, array $data = [], int $status = 200, array $headers = []): static
    {
        // Используем View фасад для генерации HTML
        $html = \Faravel\Support\Facades\View::make($viewName, $data);
        $this->setContent($html)
            ->status($status)
            ->withHeaders($headers);
        return $this;
    }

    /**
     * Сформировать ответ для скачивания файла. Устанавливает
     * заголовки Content-Type и Content-Disposition. Если имя файла
     * не указано, будет использоваться basename исходного пути.
     */
    public function download(string $filePath, ?string $name = null, array $headers = [], int $status = 200): static
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new \RuntimeException("File for download not found: {$filePath}");
        }
        $filename = $name ?: basename($filePath);
        // Пытаемся определить MIME‑тип
        $mime = function_exists('mime_content_type') ? mime_content_type($filePath) : 'application/octet-stream';
        $content = file_get_contents($filePath);
        $this->setContent($content)
            ->status($status)
            ->setHeader('Content-Type', $mime)
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
        // Дополнительные заголовки
        $this->withHeaders($headers);
        return $this;
    }

    /**
     * Установка контента
     */
    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Ответ в виде plain text
     */
    public function plain(string $text, int $status = 200): static
    {
        return $this->status($status)
            ->setHeader('Content-Type', 'text/plain; charset=utf-8')
            ->setContent($text);
    }

    /**
     * Ответ в формате JSON
     */
    public function json(array $data, int $status = 200): static
    {
        return $this->status($status)
            ->setHeader('Content-Type', 'application/json')
            ->setContent(json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Перенаправление
     */
    public function redirect(string $url, int $status = 302): never
    {
        $this->status($status)
            ->setHeader('Location', $url)
            ->send();
        exit;
    }

    /**
     * Отправка ответа клиенту
     */
    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $key => $value) {
            header("$key: $value", true);
        }

        foreach ($this->cookies as $cookie) {
            $key   = $cookie['key'];
            $value = $cookie['value'];
            $expire = $cookie['expire'] ?? 0;
            $path  = $cookie['path'] ?? '/';
            // Дополнительные параметры: домен, secure и httpOnly
            $domain = $cookie['domain'] ?? '';
            $secure = $cookie['secure'] ?? false;
            $httponly = $cookie['httponly'] ?? false;
            setcookie($key, $value, $expire, $path, $domain, $secure, $httponly);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
            echo $this->content;
        }
    }

    /**
     * Быстрое создание ответа (аналог Laravel::response()->make())
     */
    public static function make(string $content = '', int $status = 200, array $headers = []): static
    {
        return (new static())
            ->status($status)
            ->withHeaders($headers)
            ->setContent($content);
    }

    /**
     * Регистрация макроса
     */
    public static function macro(string $name, callable $callback): void
    {
        static::$macros[$name] = $callback;
    }

    /**
     * Проверка наличия макроса
     */
    public static function hasMacro(string $name): bool
    {
        return array_key_exists($name, static::$macros);
    }

    /**
     * Вызов макроса динамически
     */
    public function __call(string $method, array $args)
    {
        if (static::hasMacro($method)) {
            return static::$macros[$method]->bindTo($this, static::class)(...$args);
        }

        throw new \BadMethodCallException("Method {$method} does not exist.");
    }

    /**
     * Вызов статического макроса
     */
    public static function __callStatic(string $method, array $args)
    {
        if (static::hasMacro($method)) {
            return static::$macros[$method](...$args);
        }

        throw new \BadMethodCallException("Static method {$method} does not exist.");
    }
}
