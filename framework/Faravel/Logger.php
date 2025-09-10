<?php

namespace Faravel;

class Logger
{
    protected string $logPath;

    public function __construct()
    {
        $this->logPath = dirname(__DIR__, 2) . '/storage/logs/app.log';

        $this->ensureLogDirectoryExists();
    }

    public function info($message)
    {
        $this->log('info', $message);
    }

    protected function ensureLogDirectoryExists()
    {
        $dir = dirname($this->logPath);

        // Создать папку, если её нет
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        // Создать пустой файл, если его нет
        if (!file_exists($this->logPath)) {
            file_put_contents($this->logPath, '');
        }
    }

    public function trace(string $message)
    {
        $this->log('trace', $message);
    }

    /**
     * Унифицированная запись в лог.
     *
     * @param string $level
     * @param string $message
     */
    public function log(string $level, string $message): void
    {
        $level = strtoupper($level);
        file_put_contents($this->logPath, "[{$level}] {$message}" . PHP_EOL, FILE_APPEND);
    }

    /**
     * Запись отладочной информации.
     */
    public function debug(string $message): void
    {
        $this->log('debug', $message);
    }

    /**
     * Запись предупреждения.
     */
    public function warning(string $message): void
    {
        $this->log('warning', $message);
    }

    /**
     * Запись ошибки.
     */
    public function error(string $message): void
    {
        $this->log('error', $message);
    }
}
