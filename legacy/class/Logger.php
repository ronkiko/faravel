<?php # class/Logger.php

class Logger
{
    private string $logFile;
    private string $errorFile;
    private bool $debug;

    public function __construct()
    {
        $this->logFile = defined('SYNC_LOG_FILE') ? SYNC_LOG_FILE : WWW_ROOT . '/sync/log/fetch_sync.log';
        $this->errorFile = defined('SYNC_ERROR_FILE') ? SYNC_ERROR_FILE : WWW_ROOT . '/sync/log/fetch_sync_errors.log';
        $this->debug = defined('SYNC_DEBUG') && SYNC_DEBUG === true;
    }

    public function log(string $msg, bool $isError = false): void
    {
        $this->write('INFO', $msg);
        if ($isError) {
            $this->write('FAIL', $msg, $this->errorFile);
        }
    }

    public function info(string $msg): void
    {
        $this->write('INFO', $msg);
    }

    public function warn(string $msg): void
    {
        $this->write('WARN', $msg);
    }

    public function fail(string $msg): void
    {
        $this->write('FAIL', $msg);
        $this->write('FAIL', $msg, $this->errorFile);
    }

    public function debug(string $msg): void
    {
        if ($this->debug) {
            $this->write('DEBUG', $msg);
        }
    }

    private function write(string $level, string $msg, ?string $fileOverride = null): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $safeMessage = $this->sanitize($msg);

        // Если сообщение уже начинается с [XXX], не добавляем повторный уровень
        if (preg_match('/^\[[A-Z]+\]/', $safeMessage)) {
            $line = "[$timestamp] $safeMessage\n";
        } else {
            $line = "[$timestamp] [$level] $safeMessage\n";
        }

        $file = $fileOverride ?? $this->logFile;
        file_put_contents($file, $line, FILE_APPEND);
    }


    private function sanitize(string $input): string
    {
        $input = strip_tags($input);
        return str_replace(["\n", "\r"], '', $input);
    }
}
