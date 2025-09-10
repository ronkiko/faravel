<?php # process_inbound.php

require dirname(__DIR__) . '/include/config.php';
require dirname(__DIR__) . '/include/db.php';
require __DIR__ . '/functions.php';

logMessage("PROCESS : Started process_inbound");

$inboundDir = __DIR__ . '/inbound';

if (!is_dir($inboundDir)) {
    logMessage("PROCESS : Inbound directory not found: $inboundDir", true);
    exit(1);
}

try {
    foreach (scandir($inboundDir) as $day) {
        if (!preg_match('/^\d+$/', $day)) continue;

        $dayPath = "$inboundDir/$day";
        if (!is_dir($dayPath)) {
            logMessage("PROCESS : Skipped non-dir $dayPath");
            continue;
        }

        foreach (scandir($dayPath) as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) !== 'json') continue;

            $eventType = basename($file, '.json');
            $fullPath = "$dayPath/$file";

            logMessage("PROCESS : Reading $eventType from $day");

            $content = @file_get_contents($fullPath);
            if ($content === false) {
                logMessage("PROCESS : Failed to read $fullPath", true);
                continue;
            }

            $decoded = json_decode($content, true);
            if (!is_array($decoded)) {
                logMessage("PROCESS : Invalid JSON in $file", true);
                continue;
            }

            // Пример разбора события user_registered
            if ($eventType === 'user_registered') {
                $user = $decoded['user'] ?? null;

                if (!is_array($user) || !isset($user['id'], $user['name'], $user['registered'])) {
                    logMessage("PROCESS : Skipped invalid user_registered missing fields", true);
                    continue;
                }

                try {
                    $pdo = getPDO();
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ?");
                    $stmt->execute([$user['id']]);

                    if ($stmt->fetchColumn() > 0) {
                        $cleanName = sanitize($user['name']);
                        logMessage("PROCESS : Skipped duplicate user {$cleanName}");
                        continue;
                    }

                    $stmt = $pdo->prepare("INSERT INTO users (id, username, password, registered) VALUES (?, ?, '', ?)");
                    $stmt->execute([
                        $user['id'],
                        $user['name'],
                        (int)$user['registered']
                    ]);

                    $cleanName = sanitize($user['name']);
                    logMessage("PROCESS : Imported user {$cleanname}");
                } catch (PDOException $e) {
                    logMessage("PROCESS : DB error — " . $e->getMessage(), true);
                }
            } else {
                logMessage("PROCESS : Unknown event type: $eventType", true);
            }
        }
    }
} catch (Throwable $e) {
    logMessage("PROCESS : Fatal error — " . $e->getMessage(), true);
}
