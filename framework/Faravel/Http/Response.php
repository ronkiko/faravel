<?php // v0.4.2
/* framework/Faravel/Http/Response.php
Purpose: HTTP-ответ Faravel: статус/заголовки/куки/контент + утилиты (view/json/redirect).
FIX: Устранено дублирование: Response::view() объявлен устаревшим и теперь проксирует
     в response()->view(...), не выполняя собственный рендер. Каноничный путь — фабрика.
*/

namespace Faravel\Http;

class Response
{
    protected int $status = 200;
    protected array $headers = [];
    protected array $cookies = [];
    protected string $content = '';

    protected static array $macros = [];

    /**
     * Базовый конструктор HTTP-ответа.
     *
     * @param string               $content  Тело ответа.
     * @param int                  $status   Код состояния HTTP.
     * @param array<string,string> $headers  Ассоц. массив заголовков.
     */
    public function __construct(string $content = '', int $status = 200, array $headers = [])
    {
        $this->setContent($content);
        $this->status($status);
        $this->withHeaders($headers);
    }

    /**
     * Установить HTTP-статус.
     *
     * @param int $status Код состояния HTTP.
     * @return static
     */
    public function status(int $status): static
    {
        $this->status = $status;
        return $this;
    }

    /**
     * Установить один заголовок.
     *
     * @param string $key   Имя заголовка.
     * @param string $value Значение заголовка.
     * @return static
     */
    public function setHeader(string $key, string $value): static
    {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * Массовая установка заголовков.
     *
     * @param array<string,string> $headers Ассоц. массив заголовков.
     * @return static
     */
    public function withHeaders(array $headers): static
    {
        foreach ($headers as $key => $value) {
            $this->setHeader((string)$key, (string)$value);
        }
        return $this;
    }

    /**
     * Установить текстовое содержимое ответа.
     *
     * @param string $content Тело ответа.
     * @return static
     */
    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Сформировать JSON-ответ.
     *
     * @param array<string,mixed>  $data    Данные для json_encode().
     * @param int                  $status  Код состояния HTTP.
     * @param array<string,string> $headers Доп. заголовки.
     * @return static
     */
    public function json(array $data, int $status = 200, array $headers = []): static
    {
        $this->status($status)
            ->setHeader('Content-Type', 'application/json')
            ->setContent(json_encode($data, JSON_UNESCAPED_UNICODE));
        foreach ($headers as $key => $value) {
            $this->setHeader((string)$key, (string)$value);
        }
        return $this;
    }

    /**
     * Вернуть представление как HTML-ответ (устаревший метод).
     * В Faravel каноничный путь — использовать фабрику:
     *   return response()->view('tpl', $data, 200, ['X-Foo' => 'bar']);
     *
     * Этот метод оставлен для обратной совместимости и ПРОКСИРУЕТ в фабрику ответов,
     * чтобы не дублировать рендер. Будет удалён в следующем мажорном релизе.
     *
     * @deprecated Use response()->view($viewName, $data, $status, $headers) instead.
     *
     * @param string               $viewName Имя вида 'a.b.c'.
     * @param array<string,mixed>  $data     Локальные данные.
     * @param int                  $status   Код состояния HTTP.
     * @param array<string,string> $headers  Доп. заголовки.
     *
     * Preconditions:
     * - Провайдеры ViewServiceProvider/ForumViewServiceProvider зарегистрированы.
     * Side effects: делегирует в фабрику ответов через глобальный helper response().
     *
     * @return static
     */
    public function view(string $viewName, array $data = [], int $status = 200, array $headers = []): static
    {
        // Emit runtime deprecation notice in dev (не критично в проде).
        if (function_exists('env') && (env('APP_ENV') === 'local' || env('APP_DEBUG'))) {
            @trigger_error(
                'Response::view() is deprecated; use response()->view(...)',
                E_USER_DEPRECATED
            );
        }

        // Делегируем фабрике и возвращаем полученный Response (новый инстанс).
        /** @var Response $resp */
        $resp = \response()->view($viewName, $data);

        // Применим желаемый статус/заголовки к результату.
        $resp->status($status)->withHeaders($headers);

        // Возвращаем объект-результат вместо "перенастройки" текущего инстанса.
        // Это совместимо с сигнатурой (возвращается Response).
        return $resp;
    }

    /**
     * Перенаправление: немедленно отправляет ответ и завершает выполнение.
     *
     * @param string $url    URL назначения.
     * @param int    $status Код состояния HTTP.
     * @return never
     */
    public function redirect(string $url, int $status = 302): never
    {
        $this->status($status)->setHeader('Location', $url)->send();
        exit;
    }

    /**
     * Отправить ответ клиенту.
     *
     * @return void
     */
    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $key => $value) {
            header($key . ': ' . $value, true);
        }

        foreach ($this->cookies as $cookie) {
            @setcookie(
                $cookie['key'],
                $cookie['value'],
                $cookie['expire'] ?? 0,
                $cookie['path'] ?? '/',
                $cookie['domain'] ?? '',
                $cookie['secure'] ?? false,
                $cookie['httponly'] ?? false
            );
        }

        echo $this->content;
    }

    /* ========================= Cookies ========================= */

    /**
     * Установить cookie с минимальным набором параметров.
     *
     * @param string $key    Имя cookie.
     * @param string $value  Значение.
     * @param int    $expire Время жизни (UNIX time) или 0.
     * @param string $path   Путь.
     * @return static
     */
    public function setCookie(string $key, string $value, int $expire = 0, string $path = '/'): static
    {
        $this->cookies[] = compact('key', 'value', 'expire', 'path');
        return $this;
    }

    /**
     * Установить cookie с расширенными параметрами.
     *
     * @param string $key
     * @param string $value
     * @param int    $expire
     * @param string $path
     * @param string $domain
     * @param bool   $secure
     * @param bool   $httponly
     * @return static
     */
    public function cookie(
        string $key,
        string $value,
        int $expire = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httponly = false
    ): static {
        $this->cookies[] = compact('key', 'value', 'expire', 'path', 'domain', 'secure', 'httponly');
        return $this;
    }
}
