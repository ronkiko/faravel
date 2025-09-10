<?php

class EventHandler
{
    private Logger $logger;
    private bool $debug;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->debug = defined('SYNC_DEBUG') && SYNC_DEBUG === true;
    }

    public function importInboundEvents(): void
    {
        $baseDir = WWW_ROOT . '/sync/inbound';

        if (!is_dir($baseDir)) {
            $this->logger->log("INBOUND: directory not found: $baseDir", true);
            return;
        }

        $this->debug && $this->logger->debug("INBOUND: scanning directory $baseDir");

        $dayDirs = array_filter(scandir($baseDir), fn($f) => is_dir("$baseDir/$f") && preg_match('/^\d+$/', $f));

        foreach ($dayDirs as $day) {
            $dir = "$baseDir/$day";
            $files = glob("$dir/*.json");

            $this->debug && $this->logger->debug("INBOUND: processing directory $dir, files found: " . count($files));

            foreach ($files as $file) {
                $filename = basename($file);
                if (!preg_match('/^(.+?)_(.+?)\.json$/', $filename, $matches)) {
                    $this->logger->log("INBOUND: invalid filename format: $filename", true);
                    continue;
                }

                [$full, $peer, $eventType] = $matches;

                $this->debug && $this->logger->debug("INBOUND: processing file $filename (peer: $peer, event: $eventType)");

                $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $this->debug && $this->logger->debug("INBOUND: $filename has " . count($lines) . " lines");

                $success = true;

                foreach ($lines as $i => $line) {
                    $lineNumber = $i + 1;
                    $payload = json_decode($line, true);

                    if (!is_array($payload)) {
                        $this->logger->log("INBOUND: invalid JSON at $filename line $lineNumber", true);
                        $success = false;
                        continue;
                    }

                    $this->debug && $this->logger->debug("INBOUND: line $lineNumber payload parsed successfully");

                    try {
                        $this->handleEvent($eventType, $payload, $lineNumber);
                    } catch (\Throwable $e) {
                        $this->logger->log("INBOUND: error in $eventType at line $lineNumber: " . $e->getMessage(), true);
                        $success = false;
                    }
                }

                // Удаляем файл, если успешно
                if ($success) {
                    if (unlink($file)) {
                        $this->logger->debug("INBOUND: deleted successfully: $filename");
                    } else {
                        $this->logger->log("INBOUND: failed to delete file: $filename", true);
                    }

                    // Если папка теперь пуста — удаляем её
                    $remaining = array_diff(scandir($dir), ['.', '..']);
                    if (empty($remaining)) {
                        if (rmdir($dir)) {
                            $this->logger->debug("INBOUND: removed empty folder $dir");
                        } else {
                            $this->logger->log("INBOUND: failed to remove folder $dir", true);
                        }
                    }
                }
            }
        }
    }


    public function handleEvent(string $type, array $payload, int $lineNumber = 0): void
    {
        $this->debug && $this->logger->debug("INBOUND: dispatching event type $type @ line $lineNumber");

        switch ($type) {
            case 'UserCreated':
                $this->handleUserCreated($payload, $lineNumber);
                break;

            // Примеры для будущих событий:
            // case 'UserUpdated':
            //     $this->handleUserUpdated($payload, $lineNumber);
            //     break;

            // case 'UserDeleted':
            //     $this->handleUserDeleted($payload, $lineNumber);
            //     break;

            default:
                $this->logger->log("INBOUND: unknown event type: $type", true);
                break;
        }
    }

    private function handleUserCreated(array $user, int $lineNumber = 0): void
    {
        $this->debug && $this->logger->debug("INBOUND: handling UserCreated @ line $lineNumber");

        // Поддержка нового формата: {type: ..., day: ..., event: {...}}
        if (isset($user['event']) && is_array($user['event'])) {
            $user = $user['event'];
        }

        if (!isset($user['id'], $user['name'], $user['password'], $user['registered'])) {
            $this->logger->log("INBOUND: skipped invalid UserCreated — missing fields at line $lineNumber", true);
            return;
        }

        try {
            $pdo = getPDO();

            // Пропускаем, если пользователь уже существует
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            if ($stmt->fetchColumn() > 0) {
                $this->logger->log("INBOUND: skipped duplicate user {$this->sanitize($user['name'])}");
                return;
            }

            // Вставка нового пользователя
            $stmt = $pdo->prepare("
            INSERT INTO users (
                id, username, password, registered,
                last_visit, last_post,
                role_id, language, title, style, signature
            )
            VALUES (
                :id, :username, :password, :registered,
                :last_visit, :last_post,
                :role_id, :language, :title, :style, :signature
            )
        ");

            $stmt->execute([
                ':id'         => $user['id'],
                ':username'   => $user['name'],
                ':password'   => $user['password'],
                ':registered' => (int)$user['registered'],
                ':last_visit' => $user['last_visit'] ?? null,
                ':last_post'  => $user['last_post'] ?? null,
                ':role_id'    => $user['role_id'] ?? 1,
                ':language'   => $user['language'] ?? 1,
                ':title'      => $user['title'] ?? 0,
                ':style'      => $user['style'] ?? 0,
                ':signature'  => $user['signature'] ?? null,
            ]);

            $this->logger->log("INBOUND: imported user {$this->sanitize($user['name'])}");
        } catch (PDOException $e) {
            $this->logger->log("INBOUND: DB error — " . $e->getMessage(), true);
        }
    }


    private function sanitize(string $input): string
    {
        $input = strip_tags($input);
        $input = str_replace(["\n", "\r"], '', $input);
        return preg_replace('/[^a-zA-Z0-9 _:\\-\.]/', '', $input);
    }
}
