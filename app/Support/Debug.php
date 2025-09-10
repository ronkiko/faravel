<?php // v0.4.1
/* app/Support/Debug.php
Purpose: Утилита отладочного логирования для Faravel. Пишет компактные JSON-сообщения
в error_log (видно в `docker logs`). Помогает отследить порядок регистрации
провайдеров, сборку middleware и обращения к контейнеру (auth).
FIX: новый файл — добавлены методы log() и shortTrace().
*/

namespace App\Support;

final class Debug
{
    /**
     * Записать отладочное сообщение в error_log с единым префиксом.
     *
     * @param string               $event Короткий код события (например, AUTH.MAKE.PRE).
     * @param array<string,mixed>  $ctx   Произвольный контекст (будет json_encode()).
     * @return void
     */
    public static function log(string $event, array $ctx = []): void
    {
        // IMPORTANT: keep it safe and short; no exceptions on logging.
        $payload = [
            't' => date('c'),
            'e' => $event,
            'c' => $ctx,
        ];
        // error_log writes to FPM stderr → видно в docker logs
        @error_log('[FARAVEL][DBG] ' . json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    /**
     * Короткий стек вызовов без аргументов, удобно для логов.
     *
     * @param int $limit Максимум кадров (по умолчанию 10).
     * @return array<int,string> Список строк вида "Class::method() file:line".
     */
    public static function shortTrace(int $limit = 10): array
    {
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $limit);
        $out = [];
        foreach ($bt as $i => $f) {
            $cls = $f['class'] ?? '';
            $typ = $f['type'] ?? '';
            $fn  = $f['function'] ?? '';
            $fl  = $f['file'] ?? '';
            $ln  = $f['line'] ?? 0;
            $sig = ($cls ? ($cls . $typ) : '') . $fn . '()';
            $loc = $fl ? ($fl . ':' . $ln) : '';
            $out[] = ($i . ':' . $sig . ($loc ? ' @ ' . $loc : ''));
        }
        return $out;
    }
}
