<?php // v0.4.5
/* app/Support/Logger.php
Purpose: Упрощённый логгер проекта. Пишет события разработки в storage/logs/debug.log.
FIX: Добавлен метод exception() для логирования исключений со стеком и контекстом.
*/
namespace App\Support;

final class Logger
{
    /**
     * Write a debug line into debug.log.
     *
     * @param string               $tag  Short tag like 'ROUTER.DISPATCH'
     * @param string|int|float     $data Message payload
     * @return void
     */
    public static function log(string $tag, string|int|float $data): void
    {
        $dir = \base_path('storage/logs');
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $line = '[' . $tag . "]\t" . (string)$data . PHP_EOL;
        @file_put_contents($dir . '/debug.log', $line, FILE_APPEND);
    }

    /**
     * Log exception with class, message, file:line and trimmed stacktrace.
     *
     * @param string     $tag   Category tag
     * @param \Throwable $e     Exception instance
     * @param array<string,mixed> $ctx Optional context (will be json-encoded)
     * @return void
     */
    public static function exception(string $tag, \Throwable $e, array $ctx = []): void
    {
        $base = \get_class($e) . "\t" . $e->getMessage() . "\t" . $e->getFile() . ':' . $e->getLine();
        if ($ctx !== []) {
            $base .= "\t" . json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        self::log($tag . '.ERROR', $base);

        $trace = $e->getTraceAsString();
        // Trim very long traces to keep log readable
        if (\strlen($trace) > 4000) {
            $trace = substr($trace, 0, 4000) . '... [truncated]';
        }
        self::log($tag . '.TRACE', $trace);
    }
}
