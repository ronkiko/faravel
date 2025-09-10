<?php # sync.class.php

class SyncManager
{
    private string $mode;
    private array $peers;
    private string $self;
    private string $apiKey;
    private string $selfSalt;
    private int $fetchDaysBack;
    private bool $debug;

    public function __construct(string $mode = 'incoming')
    {
        $this->mode = $mode;
        $this->peers = SYNC_PEERS;
        $this->self = SYNC_SELF;
        $this->apiKey = SYNC_API_KEY;
        $this->selfSalt = SYNC_PEERS[SYNC_SELF]['salt'];
        $this->fetchDaysBack = defined('FETCH_DAYS_BACK') ? max(1, (int)SYNC_DAYS_BACK) : 1;
        $this->debug = defined('SYNC_DEBUG') && SYNC_DEBUG === true;
    }

    public function run(): void
    {
        match ($this->mode) {
            'incoming' => $this->handleIncoming(),
            'outgoing' => $this->handleOutbound(),
            'inbound'  => $this->handleInbound(),
            default    => $this->log("Unknown mode: {$this->mode}", true),
        };
    }

    private function handleIncoming(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->authorizePeer();
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processIncomingRequest();
        }
    }

    private function handleOutbound(): void
    {
        $eventTypes = ['UserCreated'];
        $days = isset($_GET['days']) ? max(1, (int)$_GET['days']) : $this->fetchDaysBack;
        $today = floor(time() / 86400);
        $startDay = $today - ($days - 1);

        foreach ($this->peers as $peerAlias => $peerData) {
            if ($peerAlias === $this->self || !isset($peerData['url'], $peerData['salt'])) continue;

            $peerUrl  = rtrim($peerData['url'], '/');
            $peerSalt = $peerData['salt'];
            $sig = hash_hmac('sha256', "peer={$this->self}", $this->apiKey . $this->selfSalt . $peerSalt);
            $authUrl = "$peerUrl/sync.php?peer={$this->self}&sig=$sig";

            $ch = curl_init($authUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
            ]);
            $resp = curl_exec($ch);
            curl_close($ch);

            $first = json_decode(explode("\n", trim($resp))[0] ?? '', true);
            if (!is_array($first) || $first['status'] !== 'authorized') {
                $this->log("[FAIL] Not authorized by $peerAlias", true);
                continue;
            }

            $this->log("[START] Session with $peerAlias started");

            for ($i = 0; $i < $days; $i++) {
                $day = $startDay + $i;

                foreach ($eventTypes as $type) {
                    $query = ['type' => $type, 'day' => $day];
                    $json = json_encode($query);

                    if ($this->debug) {
                        $payload = [
                            'peer' => $this->self,
                            'data' => $json, // в debug-режиме отправляем сырой JSON
                        ];
                    } else {
                        $key = hash('sha256', $this->apiKey . $this->selfSalt . $peerSalt, true);
                        $iv = random_bytes(16);
                        $enc = openssl_encrypt($json, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
                        $payload = [
                            'peer' => $this->self,
                            'data' => base64_encode($iv . $enc),
                        ];
                    }

                    $ch = curl_init("$peerUrl/sync.php");
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 10,
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => json_encode($payload),
                        CURLOPT_HTTPHEADER => ['Content-Type: application/json']
                    ]);
                    $response = curl_exec($ch);
                    curl_close($ch);

                    $lines = explode("\n", trim($response));
                    $savedCount = 0;

                    foreach ($lines as $line) {
                        $line = trim($line);
                        if ($line === '') continue;

                        if ($this->debug) {
                            $pt = $line;
                        } else {
                            $decodedLine = base64_decode($line, true);
                            if (!is_string($decodedLine)) {
                                $this->log("Invalid base64 line from $peerAlias @ day $day", true);
                                continue;
                            }

                            if (strlen($decodedLine) < 17) {
                                $this->log("Invalid encrypted line from $peerAlias @ day $day", true);
                                continue;
                            }
                            $iv = substr($decodedLine, 0, 16);
                            $ct = substr($decodedLine, 16);
                            $key = hash('sha256', $this->apiKey . $this->selfSalt . $peerSalt, true);
                            $pt = openssl_decrypt($ct, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
                            if (!is_string($pt)) {
                                $this->log("Decryption failed from $peerAlias @ day $day", true);
                                continue;
                            }
                        }

                        $decodedJson = json_decode($pt, true);
                        if (is_array($decodedJson) && isset($decodedJson['status'])) {
                            if ($decodedJson['status'] === 'no_data') {
                                $this->log("[INFO] No data from $peerAlias for $type @ day $day");
                                continue 2;
                            }
                            if ($decodedJson['status'] === 'sending') {
                                $this->log("[INFO] Response from $peerAlias @ day $day ($type):");
                                continue;
                            }
                            if ($decodedJson['status'] === 'disconnected') {
                                continue;
                            }
                        }

                        $targetDir = __DIR__ . "/../sync/inbound/$day";
                        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
                        $targetFile = "$targetDir/{$peerAlias}_{$type}.json";

                        static $writtenFiles = [];
                        $isFirstWrite = !isset($writtenFiles[$targetFile]);
                        $fh = fopen($targetFile, $isFirstWrite ? 'w' : 'a');
                        $writtenFiles[$targetFile] = true;

                        fwrite($fh, trim($pt) . "\n");
                        fclose($fh);
                        $savedCount++;
                    }

                    if ($savedCount > 0) {
                        $this->log("[INFO] Saved $savedCount events from $peerAlias for $type @ day $day");
                    }
                }
            }

            $this->log("[END] Session with $peerAlias closed");
        }
    }


    private function handleInbound(): void
    {
        $inboundDir = __DIR__ . '/../sync/inbound';

        if (!is_dir($inboundDir)) {
            $this->log("INBOUND: directory not found: $inboundDir", true);
            return;
        }

        foreach (scandir($inboundDir) as $day) {
            if (!preg_match('/^\d+$/', $day)) continue;

            $dayPath = "$inboundDir/$day";
            if (!is_dir($dayPath)) continue;

            foreach (scandir($dayPath) as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) !== 'json') continue;

                $eventType = preg_replace('/^[^_]+_/', '', basename($file, '.json'));
                $fullPath = "$dayPath/$file";

                $this->log("INBOUND: reading $eventType from day $day");

                $handle = fopen($fullPath, 'r');
                if (!$handle) {
                    $this->log("INBOUND: failed to open $fullPath", true);
                    continue;
                }

                $success = true;
                $lineNumber = 0;

                while (($line = fgets($handle)) !== false) {
                    $lineNumber++;
                    $line = trim($line);
                    if ($line === '') continue;

                    $decoded = json_decode($line, true);
                    if (!is_array($decoded)) {
                        $this->log("INBOUND: invalid JSON in $file at line $lineNumber", true);
                        $success = false;
                        continue;
                    }

                    switch ($eventType) {
                        case 'UserCreated':
                            try {
                                $this->handleUserCreated($decoded, $lineNumber);
                            } catch (Exception $e) {
                                $this->log("INBOUND: error in UserCreated at line $lineNumber: " . $e->getMessage(), true);
                                $success = false;
                            }
                            break;
                        default:
                            $this->log("INBOUND: unknown event type: $eventType", true);
                            $success = false;
                            break;
                    }
                }

                fclose($handle);

$this->log("INBOUND: test: $eventType", true);

                // Удаляем файл, если всё прошло успешно
                if ($success) {
                    if (unlink($fullPath)) {
                        $this->log("INBOUND: successfully deleted $file");
                    } else {
                        $this->log("INBOUND: failed to delete $file", true);
                    }

                    // Если папка пуста — удалить её
                    $remaining = array_diff(scandir($dayPath), ['.', '..']);
                    if (empty($remaining)) {
                        if (rmdir($dayPath)) {
                            $this->log("INBOUND: removed empty folder $dayPath");
                        } else {
                            $this->log("INBOUND: failed to remove folder $dayPath", true);
                        }
                    }
                }
            }
        }
    }


    private function handleUserCreated(array $user, int $lineNumber = 0): void
    {
        if (!isset($user['id'], $user['name'], $user['password'], $user['registered'])) {
            $this->log("INBOUND: skipped invalid UserCreated — missing fields at line $lineNumber", true);
            return;
        }

        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);

            if ($stmt->fetchColumn() > 0) {
                $cleanName = $this->sanitize($user['name']);
                $this->log("INBOUND: skipped duplicate user {$cleanName}");
                return;
            }

            $stmt = $pdo->prepare("
                INSERT INTO users (
                    id, username, password, registered,
                    last_visit, last_post,
                    role, language, title, style, signature
                )
                VALUES (
                    :id, :username, :password, :registered,
                    :last_visit, :last_post,
                    :role, :language, :title, :style, :signature
                )
            ");

            $stmt->execute([
                ':id'         => $user['id'],
                ':username'   => $user['name'],
                ':password'   => $user['password'],
                ':registered' => (int)$user['registered'],
                ':last_visit' => $user['last_visit'] ?? null,
                ':last_post'  => $user['last_post'] ?? null,
                ':role'       => $user['role'] ?? 1,
                ':language'   => $user['language'] ?? 1,
                ':title'      => $user['title'] ?? 0,
                ':style'      => $user['style'] ?? 0,
                ':signature'  => $user['signature'] ?? null,
            ]);

            $cleanName = $this->sanitize($user['name']);
            $this->log("INBOUND: imported user {$cleanName}");
        } catch (PDOException $e) {
            $this->log("INBOUND: DB error — " . $e->getMessage(), true);
        }
    }

    private function authorizePeer(): void
    {
        $peer = $_GET['peer'] ?? '';
        $sig = $_GET['sig'] ?? '';
        $peerSalt = $this->peers[$peer]['salt'] ?? null;

        if (!$peer || !$sig || !$peerSalt) {
            echo json_encode(['status' => 'error', 'msg' => 'Invalid auth request']);
            return;
        }

        $expectedSig = hash_hmac('sha256', "peer=$peer", $this->apiKey . $peerSalt . $this->selfSalt);
        if (!hash_equals($expectedSig, $sig)) {
            echo json_encode(['status' => 'unauthorized']);
            return;
        }

        echo json_encode(['status' => 'authorized']);
    }

    private function processIncomingRequest(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!isset($input['peer'], $input['data'])) {
            return;
        }

        $peer = $input['peer'];
        $peerSalt = $this->peers[$peer]['salt'] ?? null;
        if (!$peerSalt) {
            return;
        }

        if ($this->debug) {
            // В debug-режиме — принимаем открытый JSON без base64
            $query = json_decode($input['data'], true);
        } else {
            $raw = base64_decode($input['data'], true);
            if (!is_string($raw) || strlen($raw) < 17) {
                return;
            }

            $iv = substr($raw, 0, 16);
            $ct = substr($raw, 16);
            $key = hash('sha256', $this->apiKey . $peerSalt . $this->selfSalt, true);
            $json = openssl_decrypt($ct, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            $query = json_decode($json, true);
        }

        if (!is_array($query) || !isset($query['type'], $query['day'])) {
            return;
        }

        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT data FROM events WHERE type = ? AND created DIV 86400 = ?");
        $stmt->execute([$query['type'], (int)$query['day']]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if ($this->debug) {
            if (empty($rows)) {
                echo json_encode(['status' => 'no_data']) . "\n";
                return;
            }
            echo json_encode(['status' => 'sending']) . "\n";
            foreach ($rows as $row) {
                echo $row . "\n";
            }
            echo json_encode(['status' => 'disconnected']) . "\n";
            return;
        }

        $key = hash('sha256', $this->apiKey . $peerSalt . $this->selfSalt, true);

        if (empty($rows)) {
            $this->sendEncryptedLine(['status' => 'no_data'], $key);
            return;
        }

        $this->sendEncryptedLine(['status' => 'sending'], $key);
        foreach ($rows as $row) {
            $this->sendEncryptedLine($row, $key, false);
        }
        $this->sendEncryptedLine(['status' => 'disconnected'], $key);
    }


    private function sendEncryptedLine($data, string $key, bool $asJson = true): void
    {
        $payload = $asJson ? json_encode($data) : $data;
        $iv = random_bytes(16);
        $enc = openssl_encrypt($payload, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        echo base64_encode($iv . $enc) . "\n";
    }

    public function log(string $msg, bool $isError = false): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $safeMessage = $this->sanitize($msg);

        $logFile = defined('SYNC_LOG_FILE') ? SYNC_LOG_FILE : __DIR__ . '/../sync/log/fetch_sync.log';
        $errorFile = defined('SYNC_ERROR_FILE') ? SYNC_ERROR_FILE : __DIR__ . '/../sync/log/fetch_sync_errors.log';

        file_put_contents($logFile, "[$timestamp] $safeMessage\n", FILE_APPEND);
        if ($isError) {
            file_put_contents($errorFile, "[$timestamp] $safeMessage\n", FILE_APPEND);
        }
    }

    public function sanitize(string $input): string
    {
        $input = strip_tags($input);
        $input = str_replace(["\n", "\r"], '', $input);
        return preg_replace('/[^a-zA-Z0-9 _:\\-\.]/', '', $input);
    }
}
