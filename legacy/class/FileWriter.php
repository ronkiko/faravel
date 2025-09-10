<?php

class FileWriter
{
    private Logger $logger;
    private array $writtenFiles = [];
    private string $baseDir;
    private bool $debug;

    public function __construct(Logger $logger, string $baseDir = WWW_ROOT . '/sync/inbound')
    {
        $this->logger = $logger;
        $this->baseDir = $baseDir;
        $this->debug = defined('SYNC_DEBUG') && SYNC_DEBUG === true;
    }

    public function appendEvent(string $peerAlias, string $eventType, int $day, string $payload): bool
    {
        $dir = "{$this->baseDir}/$day";
        if (!is_dir($dir) && !mkdir($dir, 0777, true)) {
            $this->logger->log("Failed to create directory: $dir", true);
            return false;
        }

        $filename = "$dir/{$peerAlias}_{$eventType}.json";
        $isFirstWrite = !isset($this->writtenFiles[$filename]);
        $this->writtenFiles[$filename] = true;

        $mode = $isFirstWrite ? 'w' : 'a';
        $fh = fopen($filename, $mode);
        if (!$fh) {
            $this->logger->log("Failed to open file: $filename", true);
            return false;
        }

        if ($this->debug) {
            $this->logger->debug("FILEWRITER: opening $filename for " . ($isFirstWrite ? 'writing' : 'appending'));
        }

        $success = false;
        if (flock($fh, LOCK_EX)) {
            $line = rtrim($payload, "\r\n") . "\n";
            fwrite($fh, $line);
            flock($fh, LOCK_UN);
            $success = true;
            if ($this->debug) {
                $this->logger->debug("FILEWRITER: wrote 1 line to $filename");
            }
        } else {
            $this->logger->log("Failed to lock file: $filename", true);
        }

        fclose($fh);
        return $success;
    }
}
