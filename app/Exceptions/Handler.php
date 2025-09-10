<?php

namespace App\Exceptions;

use Throwable;
use Faravel\Logger;
use Faravel\Http\Response;

/**
 * Глобальный обработчик исключений.
 * Логирует ошибку и возвращает корректный HTTP‑ответ.
 */
class Handler
{
    protected Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Обработать исключение и вернуть HTTP‑ответ.
     *
     * @param Throwable $e
     * @return Response
     */
    public function handle(Throwable $e): Response
    {
        error_log('[Exception] ' . $e->getMessage());
        // Обрабатываем исключение авторизации отдельно: возвращаем 403
        if ($e instanceof \Faravel\Exceptions\AuthorizationException) {
            $this->logger->warning($e->getMessage());
            $message = config('app.debug', false)
                ? 'Authorization exception: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
                : 'Forbidden';
            return (new Response())->status(403)->setContent($message);
        }
        // Логируем сообщение об ошибке
        $this->logger->error($e->getMessage());

        // В режиме debug показываем сообщение исключения, иначе — общее сообщение
        $debug = config('app.debug', false);
        $message = $debug
            ? 'Unhandled exception: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
            : 'Internal Server Error';

        // Если у исключения есть метод getCode() и он в диапазоне 400–599, используем его
        $code = $e->getCode();
        if (!is_int($code) || $code < 400 || $code > 599) {
            $code = 500;
        }
        return (new Response())->status($code)->setContent($message);
    }
}