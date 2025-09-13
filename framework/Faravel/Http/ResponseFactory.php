<?php // v0.4.2
/* framework/Faravel/Http/ResponseFactory.php
Purpose: Фабрика HTTP-ответов (make/json/redirect/view) для удобного хелпера response().
FIX: Добавлены параметры $status и $headers в view(); View теперь рендерится в строку и
     ставится Content-Type: text/html; charset=UTF-8. Совместимо с Laravel-стилем вызова.
*/

namespace Faravel\Http;

class ResponseFactory
{
    /**
     * Создать произвольный текстовый ответ.
     *
     * @param string               $content Тело ответа.
     * @param int                  $status  Код состояния HTTP.
     * @param array<string,string> $headers Заголовки.
     * @return Response
     */
    public function make(string $content = '', int $status = 200, array $headers = []): Response
    {
        $response = new Response();
        $response->setContent($content)->status($status)->withHeaders($headers);
        return $response;
    }

    /**
     * Создать JSON-ответ.
     *
     * @param array<string,mixed>  $data    Данные для json_encode().
     * @param int                  $status  Код состояния HTTP.
     * @param array<string,string> $headers Доп. заголовки.
     * @return Response
     */
    public function json(array $data, int $status = 200, array $headers = []): Response
    {
        $response = new Response();
        $response->status($status)
            ->setHeader('Content-Type', 'application/json')
            ->setContent(json_encode($data, JSON_UNESCAPED_UNICODE))
            ->withHeaders($headers);
        return $response;
    }

    /**
     * Подготовить ответ-редирект (без немедленного exit).
     *
     * @param string               $url     Назначение.
     * @param int                  $status  Код состояния HTTP.
     * @param array<string,string> $headers Заголовки (добавляются к Location).
     * @return Response
     */
    public function redirect(string $url, int $status = 302, array $headers = []): Response
    {
        $response = new Response();
        return $response->status($status)->withHeaders($headers)->setHeader('Location', $url);
    }

    /**
     * Создать HTML-ответ из View/Blade.
     * По аналогии с Laravel: response()->view('tpl', $data, $status, $headers)
     *
     * @param string               $viewName Имя вида 'a.b.c'.
     * @param array<string,mixed>  $data     Локальные данные.
     * @param int                  $status   Код состояния HTTP (по умолчанию 200).
     * @param array<string,string> $headers  Доп. заголовки.
     *
     * Preconditions:
     * - Зарегистрированы провайдеры ViewServiceProvider/ForumViewServiceProvider.
     *
     * Side effects:
     * - Рендерит шаблон (файловая система), формирует HTML-строку.
     *
     * @return Response
     * @example return response()->view('forum.index', ['vm' => $vm], 200, ['X-Foo' => 'bar']);
     */
    public function view(
        string $viewName,
        array $data = [],
        int $status = 200,
        array $headers = []
    ): Response {
        $view = \Faravel\Support\Facades\View::make($viewName, $data);
        // Render to string to keep Response content strictly string.
        $html = method_exists($view, 'render') ? $view->render() : (string)$view; // stringable fallback

        // Ensure HTML content-type (explicit like Laravel's default behavior).
        $response = $this->make($html, $status, $headers);
        $response->setHeader('Content-Type', 'text/html; charset=UTF-8');
        return $response;
    }
}
