<?php # admin/admin_sync.php

require dirname(__DIR__) . '/bootstrap.php';

if (!IS_LOGGED_IN) {
    http_response_code(403);
    exit('Forbidden: Not logged in');
}
if (!isset($_SESSION['user']['name']) || $_SESSION['user']['name'] !== 'admin') {
    // http_response_code(403);
    // exit('Forbidden: Not admin');
}

define('MAX_LOG_BYTES', 1024 * 1024); // 1MB

function read_trimmed_log(string $path): string {
    if (!file_exists($path)) return 'Log not found.';

    $size = filesize($path);
    if ($size === false) return 'Error reading log file.';

    if ($size <= MAX_LOG_BYTES) {
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) return 'Error reading log.';
        return implode("\n", $lines);
    }

    $fh = fopen($path, 'r+');
    if (!$fh) return 'Error opening log file.';

    fseek($fh, -MAX_LOG_BYTES, SEEK_END);
    $tail = stream_get_contents($fh);

    if ($tail === false) {
        fclose($fh);
        return 'Error reading log tail.';
    }

    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, $tail);
    fflush($fh);
    fclose($fh);

    $lines = explode("\n", trim($tail));
    return implode("\n", $lines);
}

// === Очистка логов ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear_main'])) {
        @file_put_contents(SYNC_LOG_FILE, '');
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '#') . '#mainlog_cleared');
        exit;
    }
    if (isset($_POST['clear_errors'])) {
        @file_put_contents(SYNC_ERROR_FILE, '');
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '#') . '#errorlog_cleared');
        exit;
    }
    if (isset($_POST['trigger_outbound'])) {
        header('Location: /sync.php?mode=outgoing');
        exit;
    }
    if (isset($_POST['trigger_process'])) {
        header('Location: /sync.php?mode=inbound');
        exit;
    }
    if (isset($_POST['trigger_scheduler'])) {
        die('not implemented');
        header('Location: /sync.php?mode=inbound');
        exit;
    }
}

// === Папки ===
$logDir      = SYNC_LOG_DIR;
$inboundDir  = WWW_ROOT . '/sync/inbound';

// === Логи ===
$logs = [
    'main'   => read_trimmed_log(SYNC_LOG_FILE),
    'errors' => read_trimmed_log(SYNC_ERROR_FILE),
];

// === INBOUND ===
$inbound = [];

if (is_dir($inboundDir)) {
    foreach (scandir($inboundDir) as $day) {
        if (!preg_match('/^\d+$/', $day)) continue;
        $dayPath = "$inboundDir/$day";
        if (!is_dir($dayPath)) continue;

        foreach (scandir($dayPath) as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) !== 'json') continue;
            $fullPath = "$dayPath/$file";
            $lines = @file($fullPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) continue;

            $matches = [];
            if (preg_match('/^([a-zA-Z0-9_-]+)_(.+)\\.json$/', $file, $matches)) {
                $peer = $matches[1];
                $eventType = $matches[2];
                $label = "$peer → $eventType";
            } else {
                $eventType = basename($file, '.json');
                $label = $eventType;
            }

            $parsed = [];
            $validCount = 0;

            foreach ($lines as $lineNum => $line) {
                $line = trim($line);
                if ($line === '') continue;

                $json = json_decode($line, true);
                if (is_array($json)) {
                    $parsed[] = $json;
                    $validCount++;
                } else {
                    $parsed[] = [
                        '_error' => 'Invalid JSON',
                        '_line'  => $lineNum + 1,
                        '_raw'   => $line
                    ];
                }
            }

            if (!empty($parsed)) {
                $pretty = json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                $inbound[$day][$label] = [
                    'count' => $validCount,
                    'pretty' => htmlspecialchars($pretty)
                ];
            } else {
                $inbound[$day][$label] = [
                    'count' => 0,
                    'pretty' => '(Empty or invalid content)'
                ];
            }
        }
    }
}

// === INBOUND HTML ===
$inbound_html = '';
krsort($inbound);
if (empty($inbound)) {
    $inbound_html = '<p>No inbound data found.</p>';
} else {
    foreach ($inbound as $day => $types) {
        $inbound_html .= '<h3>Day ' . htmlspecialchars($day) . '</h3>';
        foreach ($types as $label => $entry) {
            $inbound_html .= '<details>';
            $inbound_html .= '<summary>' . htmlspecialchars($label) . ' (' . $entry['count'] . ' events)</summary>';
            $inbound_html .= '<pre style="background:#eef;border:1px solid #99c;padding:10px;overflow:auto;">';
            $inbound_html .= $entry['pretty'];
            $inbound_html .= '</pre>';
            $inbound_html .= '</details>';
        }
    }
}

// === OUTBOUND FROM DATABASE ===
$outbound = [];
try {
    $pdo = getPDO();
    $stmt = $pdo->query("SELECT * FROM events ORDER BY created DESC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $day = floor($row['created'] / 86400); // days since Unix epoch
        $type = $row['type'];

        $parsed = [
            'hash' => $row['hash'],
            'type' => $type,
            'node' => $row['node'],
            'created' => $row['created'],
            'data' => json_decode($row['data'], true),
        ];

        $outbound[$day][$type][] = $parsed;
    }
} catch (Exception $e) {
    $outbound['error'] = [['message' => $e->getMessage()]];
}

// === OUTBOUND HTML ===
$outbound_html = '';
krsort($outbound);
if (empty($outbound)) {
    $outbound_html = '<p>No outbound data found.</p>';
} else {
    foreach ($outbound as $day => $types) {
        $outbound_html .= '<h3>Day ' . htmlspecialchars($day) . '</h3>';
        foreach ($types as $type => $entries) {
            $pretty = json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $outbound_html .= '<details>';
            $outbound_html .= '<summary>' . htmlspecialchars($type) . ' (' . count($entries) . ' events)</summary>';
            $outbound_html .= '<pre style="background:#fef;border:1px solid #c99;padding:10px;overflow:auto;">';
            $outbound_html .= htmlspecialchars($pretty);
            $outbound_html .= '</pre>';
            $outbound_html .= '</details>';
        }
    }
}

// === Передача в шаблон ===
$data = [
    'title'         => 'Admin: Sync Logs & Inbound',
    'logs_main'     => $logs['main'],
    'logs_errors'   => $logs['errors'],
    'inbound_html'  => $inbound_html,
    'outbound_html' => $outbound_html,
    'csrf_token'    => csrf_token(),
];

add_style('admin');
$content = renderTemplate(HTML_ROOT . '/views/admin_sync.tpl.php', $data);
draw('Admin Sync', $content);
