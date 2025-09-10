<?php

class PeerManager
{
    private array $peers;
    private string $self;
    private string $apiKey;
    private bool $debug;

    private Logger $logger;
    private CryptoHelper $crypto;
    private FileWriter $fileWriter;

    public function __construct(Logger $logger, CryptoHelper $crypto, FileWriter $fileWriter)
    {
        $this->peers = SYNC_PEERS;
        $this->self = SYNC_SELF;
        $this->apiKey = SYNC_API_KEY;
        $this->debug = defined('SYNC_DEBUG') && SYNC_DEBUG === true;
        $this->logger = $logger;
        $this->crypto = $crypto;
        $this->fileWriter = $fileWriter;
    }

    public function authorizePeer(): void
    {
        $peer = $_GET['peer'] ?? '';
        $sig = $_GET['sig'] ?? '';

        if ($this->debug) {
            $this->logger->debug("[AUTH] Peer: $peer");
            $this->logger->debug("[AUTH] Received SIG: $sig");
        }

        if (!$peer || !$sig || !isset($this->peers[$peer])) {
            $this->logger->log("[AUTH] Incoming GET from '$peer' → REJECTED (missing params or unknown peer)");
            echo json_encode(['status' => 'error', 'msg' => 'Invalid auth request']);
            return;
        }

        // Используем централизованную проверку подписи с логами
        if (!$this->crypto->verifyAuthSignature($peer, $sig)) {
            $this->logger->log("[AUTH] Incoming GET from '$peer' → REJECTED (signature mismatch)");
            echo json_encode(['status' => 'unauthorized']);
            return;
        }

        // Авторизация прошла успешно — запускаем сессию
        session_start();
        $_SESSION['peer'] = $peer;

        $this->logger->log("[AUTH] Incoming GET from '$peer' → ACCEPTED");

        // Ответ с сессионным ID
        header("Set-Cookie: PHPSESSID=" . session_id() . "; Path=/; HttpOnly");
        echo json_encode([
            'status' => 'authorized',
            'session_id' => session_id()
        ]);
    }

    public function serverSync(): void
    {
        // === STEP 1: Проверка сессии и авторизации peer ===
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!isset($_SESSION['peer'])) {
            $this->logger->log("[POST] Incoming POST → REJECTED (no active session)");
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!isset($input['peer'], $input['data'])) {
            $this->logger->log("[POST] Incoming POST → REJECTED (missing peer/data)");
            return;
        }

        $peer = $input['peer'];

        if (!isset($this->peers[$peer])) {
            $this->logger->log("[POST] Incoming POST from unknown peer '$peer' → REJECTED (not in peer list)");
            return;
        }

        if ($_SESSION['peer'] !== $peer) {
            $this->logger->log("[POST] Incoming POST from '$peer' → REJECTED (session peer mismatch)");
            return;
        }

        $this->logger->log("[POST] Incoming POST from '$peer' → ACCEPTED");

        // === STEP 2: Распаковка и расшифровка запроса ===
        $query = $this->debug
            ? json_decode($input['data'], true)
            : json_decode(gzdecode($this->crypto->decrypt($input['data'], $peer) ?: ''), true);

        if (
            !is_array($query)
            || !isset($query['day_from'], $query['day_to'], $query['event_types'])
            || !is_array($query['event_types'])
        ) {
            $this->logger->log("[POST] Incoming POST from '$peer' → REJECTED (malformed query)");
            return;
        }

        $dayFrom = (int)$query['day_from'];
        $dayTo = (int)$query['day_to'];
        $eventTypes = $query['event_types'];

        $this->logger->log("[POST] Query range: $dayFrom → $dayTo");
        $this->logger->log("[POST] Event types: " . implode(", ", $eventTypes));

        // === STEP 3: Извлечение событий из базы данных ===
        $pdo = getPDO();
        $in  = str_repeat('?,', count($eventTypes) - 1) . '?';
        $sql = "SELECT type, created, data FROM events WHERE type IN ($in) AND created DIV 86400 BETWEEN ? AND ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([...$eventTypes, $dayFrom, $dayTo]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->logger->log("[INFO] Found " . count($rows) . " events for {$peer} from $dayFrom to $dayTo");

        // === STEP 4: Если данных нет — сообщаем и завершаем ===
        if (empty($rows)) {
            $noData = json_encode(['status' => 'no_data']);
            if ($this->debug) {
                echo $noData . "\n";
            } else {
                $gz = gzencode($noData, 9);
                $enc = $this->crypto->encrypt($gz, $peer);
                echo $enc . "\n";
            }
            return;
        }

        // === STEP 5: Упаковка и отправка событий ===
        foreach ($rows as $row) {
            $event = json_decode($row['data'], true);
            if (!is_array($event)) continue;

            $day = (int)($row['created'] / 86400);
            $type = $row['type'];

            $wrapped = [$day, [$type => $event]];
            $jsonPayload = json_encode($wrapped);

            if ($this->debug) {
                $statusLine = json_encode([
                    'status' => 'sending',
                    'crc' => crc32($jsonPayload),
                    'size' => strlen($jsonPayload)
                ]);
                echo $statusLine . "\n";
                echo $jsonPayload . "\n";
            } else {
                $gzipped = gzencode($jsonPayload, 9);
                $encrypted = $this->crypto->encrypt($gzipped, $peer);

                $statusLine = json_encode([
                    'status' => 'sending',
                    'crc' => crc32($encrypted),
                    'size' => strlen($encrypted)
                ]);
                $gzStatus = gzencode($statusLine, 9);
                $encStatus = $this->crypto->encrypt($gzStatus, $peer);

                echo $encStatus . "\n";
                echo $encrypted . "\n";
            }
        }

        // === STEP 6: Финальное сообщение о завершении передачи ===
        $finalStatus = json_encode(['status' => 'disconnected']);
        if ($this->debug) {
            echo $finalStatus . "\n";
        } else {
            $gz = gzencode($finalStatus, 9);
            $enc = $this->crypto->encrypt($gz, $peer);
            echo $enc . "\n";
        }
    }

    public function clientSync(int $dayFrom, int $dayTo, array $eventTypes): void
    {
        foreach ($this->peers as $peerAlias => $peerData) {
            if ($peerAlias === $this->self || !isset($peerData['url'], $peerData['salt'])) continue;

            // === STEP 1: Формируем URL авторизации ===
            $peerUrl = rtrim($peerData['url'], '/');
            $sig = $this->crypto->generateAuthSignature($peerAlias);
            $authUrl = "$peerUrl/sync.php?peer={$this->self}&sig=$sig";

            $this->logger->log("[START] Session with $peerAlias started");

            // === STEP 2: Авторизация и получение PHPSESSID ===
            $ch = curl_init($authUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HEADER => true,
            ]);
            $resp = curl_exec($ch);
            curl_close($ch);

            $header_size = strpos($resp, "\r\n\r\n");
            $header_text = substr($resp, 0, $header_size);
            $body_text = substr($resp, $header_size + 4);
            $first = json_decode(explode("\n", trim($body_text))[0] ?? '', true);

            if (!is_array($first) || $first['status'] !== 'authorized') {
                $this->logger->log("[FAIL] Not authorized by $peerAlias", true);
                continue;
            }

            preg_match('/Set-Cookie:\s*PHPSESSID=([^;]+)/i', $header_text, $cookieMatches);
            $sessionId = $cookieMatches[1] ?? null;

            // === STEP 3: Формируем запрос на события ===
            $query = json_encode([
                'day_from' => $dayFrom,
                'day_to' => $dayTo,
                'event_types' => $eventTypes
            ]);

            $payload = [
                'peer' => $this->self,
                'data' => $this->debug ? $query : $this->crypto->encrypt(gzencode($query, 9), $peerAlias)
            ];

            $headers = ['Content-Type: application/json'];
            if ($sessionId) {
                $headers[] = "Cookie: PHPSESSID=$sessionId";
            }

            // === STEP 4: Отправка POST-запроса с запросом на события ===
            $ch = curl_init("$peerUrl/sync.php");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => $headers
            ]);
            $response = curl_exec($ch);
            curl_close($ch);

            // === STEP 5: Обработка NDJSON-ответа построчно ===
            $lines = explode("\n", trim($response));
            $received = 0;
            $saved = 0;
            $expecting = null;

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') continue;

                // === STEP 5.1: Расшифровка и распаковка строки ===
                $decrypted = $this->debug ? $line : $this->crypto->decrypt($line, $peerAlias);
                if (!is_string($decrypted)) {
                    $this->logger->log("[WARN] Decryption failed from $peerAlias", true);
                    continue;
                }

                $payloadLine = $this->debug ? $decrypted : gzdecode($decrypted);
                if (!is_string($payloadLine)) {
                    $this->logger->log("[WARN] Gzdecode failed from $peerAlias", true);
                    continue;
                }

                $decoded = json_decode($payloadLine, true);

                // === STEP 5.2: Обработка статусных сообщений (sending, no_data, disconnected) ===
                if (is_array($decoded) && isset($decoded['status'])) {
                    if ($decoded['status'] === 'no_data') break;
                    if ($decoded['status'] === 'disconnected') break;
                    if ($decoded['status'] === 'sending' && isset($decoded['crc'], $decoded['size'])) {
                        $expecting = [
                            'crc' => (int)$decoded['crc'],
                            'size' => (int)$decoded['size']
                        ];
                        continue;
                    }
                }

                // === STEP 5.3: Обработка полезной нагрузки события ===
                if ($expecting !== null) {
                    $raw = $line;
                    $binary = $this->debug ? $raw : $this->crypto->decrypt($raw, $peerAlias);

                    if (!is_string($binary)) {
                        $this->logger->log("[ERROR] Decryption failed (binary) from $peerAlias", true);
                        $expecting = null;
                        continue;
                    }

                    $actualSize = strlen($raw);
                    $actualCrc = crc32($raw);

                    if ($actualSize !== $expecting['size'] || $actualCrc !== $expecting['crc']) {
                        $this->logger->log("[ERROR] CRC/Size mismatch from $peerAlias → expected size={$expecting['size']} crc={$expecting['crc']}, got size=$actualSize crc=$actualCrc", true);
                        $expecting = null;
                        continue;
                    }

                    $jsonPayload = $this->debug ? $binary : gzdecode($binary);
                    $eventWrapper = json_decode($jsonPayload, true);
                    $expecting = null;

                    if (!is_array($eventWrapper) || count($eventWrapper) !== 2) {
                        $this->logger->log("[ERROR] Malformed event wrapper from $peerAlias", true);
                        continue;
                    }

                    $received++;
                    [$day, $typedPayload] = $eventWrapper;
                    foreach ($typedPayload as $type => $event) {
                        $ok = $this->fileWriter->appendEvent($peerAlias, $type, $day, json_encode([
                            'type' => $type,
                            'day' => $day,
                            'event' => $event
                        ]));
                        if ($ok) $saved++;
                    }
                    continue;
                }

                // === STEP 5.4: Поддержка старого формата событий ===
                $eventMeta = json_decode($payloadLine, true);
                if (is_array($eventMeta) && isset($eventMeta['type'], $eventMeta['day'])) {
                    $received++;
                    $ok = $this->fileWriter->appendEvent($peerAlias, $eventMeta['type'], $eventMeta['day'], $payloadLine);
                    if ($ok) $saved++;
                }
            }

            // === STEP 6: Завершение сессии ===
            $this->logger->log("[INFO] Received $received, saved $saved events from $peerAlias");
            $this->logger->log("[END] Session with $peerAlias closed");
        }
    }
}
