<?php # include/functions.php

// Функция для получения всех стилей
function get_styles() {
    // Возвращаем массив стилей
    return $GLOBALS['styles'] ?? [];
}

// Функция для добавления стилей
function add_style(string $style) {
    // Добавляем стиль в глобальный массив, если его там ещё нет
    if (!in_array($style, $GLOBALS['styles'])) {
        $GLOBALS['styles'][] = $style;
    }
}

function renderTemplate($template, $data) {
    // Читаем шаблон
    $content = file_get_contents($template);

    // Подстановка значений по псевдотегам
    foreach ($data as $key => $value) {
        if (!is_scalar($value)) continue;

        // Неэкранированная вставка
        $content = str_replace('[ !' . $key . ' ]', (string)$value, $content);

        // Экранированная вставка
        $content = str_replace('[ @' . $key . ' ]', htmlspecialchars((string)$value), $content);
    }

    // Удаление оставшихся неразрешённых псевдотегов (экранированных и сырых)
#    $content = preg_replace('/\[\s*[@!]\s*[a-zA-Z0-9_]+\s*\]/', '', $content);

    return $content;
}


// Функция для отрисовки страницы с подставленными переменными
function draw(string $title, string $main_content): void
{
    // Подключаем head.tpl.php с переданным заголовком
    include HTML_ROOT . '/views/head.tpl.php';

    // Подключаем nav.tpl.php
    include HTML_ROOT . '/views/nav.tpl.php';

    // Основное содержимое страницы (после шаблонизации)
    echo '<main>';
    echo $main_content;
    echo '</main>';

    // Подключаем footer.tpl.php
    include HTML_ROOT . '/views/foot.tpl.php';
}

function debug($data) {
    echo '<div style="background-color: #f5f5f5; padding: 10px; border: 1px solid #ddd;">';
    echo '<h3>Debugging Data</h3>';
    
    // Преобразуем данные в строку с экранированным HTML-кодом
    echo '<pre>' . htmlspecialchars(print_r($data, true)) . '</pre>';
    
    echo '</div>';
}

function generateUUID(): string {
    // UUIDv4
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

/*
function send_sync_event(array $event): void {
    $eventType = $event['type'] ?? 'unknown';
    unset($event['type']);

    $day = floor(time() / 86400);
    $eventDir = dirname(BASE_ROOT) . "/sync/outbound/{$day}";
    if (!is_dir($eventDir)) {
        mkdir($eventDir, 0775, true);
    }

    $filePath = $eventDir . '/' . $eventType . '.log';
    $line = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

    $fh = fopen($filePath, 'a');
    if (!$fh) {
        throw new RuntimeException("Unable to open sync log file for writing: $filePath");
    }

    // Блокируем (будет ждать, если кто-то другой уже пишет)
    if (!flock($fh, LOCK_EX)) {
        fclose($fh);
        throw new RuntimeException("Could not lock sync log file: $filePath");
    }

    $written = fwrite($fh, $line);
    fflush($fh); // На всякий случай, гарантируем запись на диск
    flock($fh, LOCK_UN);
    fclose($fh);

    if ($written === false || $written < strlen($line)) {
        throw new RuntimeException("Failed to write full sync event to $filePath");
    }
}
*/
function logEvent(string $type, array $data): void {
    $pdo     = getPDO();
    $json    = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $hash    = md5($json);
    $node    = SYNC_SELF;
    $created = time(); // timestamp

    try {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO events (hash, type, data, node, created)
            VALUES (:hash, :type, :data, :node, :created)
        ");
        $stmt->execute([
            ':hash'    => $hash,
            ':type'    => $type,
            ':data'    => $json,
            ':node'    => $node,
            ':created' => $created
        ]);
    } catch (Throwable $e) {
        error_log('[LOG_EVENT ERROR] ' . $e->getMessage());
    }
}

function csrf_token(): string {
    $token = $_SESSION['csrf_token'] ?? '';
    return urlencode($token);
}

function check(): void {
    // === Шаг 1: Генерация токена, только если он отсутствует (первый визит)
    if (!isset($_SESSION['csrf_token'])) {
#debug('FIRST token generated');
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // === Шаг 2: Проверка токена (если он передан)
    if (!check_csrf_token()) {
        http_response_code(430);
        exit('CSRF Token Violation');
    }

    // === Шаг 3: Генерация нового токена только если это POST-запрос
    // (т.е. токен использован и успешно проверен — значит пора его заменить)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
#debug('NEW token generated !!!');
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

function check_csrf_token(): bool {
    $get  = isset($_GET['csrf_token'])  ? trim((string)$_GET['csrf_token'])  : null;
    $post = isset($_POST['csrf_token']) ? trim((string)$_POST['csrf_token']) : null;

     // === DEBUG-МОД (включается/выключается одной строкой) ===
    $debug_csrf = 0;
    if ($debug_csrf) {
        debug([
            'SESSION_token' => $_SESSION['csrf_token'] ?? '(none)',
            'POST_token'    => $_POST['csrf_token']    ?? '(not set)',
            'GET_token'     => $_GET['csrf_token']     ?? '(not set)',
            'COOKIE_token'  => $_COOKIE['csrf_token']  ?? '(not set)',
            'REQUEST_token' => $_REQUEST['csrf_token'] ?? '(not set)',
            'Method'        => $_SERVER['REQUEST_METHOD']
        ]);
    }

   // 1. Если ничего не передано — считаем безопасным (например, обычный GET-запрос)
    if ($get === null && $post === null) {
        return true;
    }

    // 2. Если токен передан в обоих — ошибка
    if ($get !== null && $post !== null) {
        return false;
    }

    // 3. Если токен передан, но в сессии его нет — ошибка (возможный хак)
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }

    // 4. Если токен есть только в GET
    if ($get !== null) {
        return hash_equals($_SESSION['csrf_token'], $get);
    }

    // 5. Если токен есть только в POST
    if ($post !== null) {
        return hash_equals($_SESSION['csrf_token'], $post);
    }

    // 6. Всё остальное — отказ
    return false;
}

// === Функция транслитерации для генерации слага ===
function slugify($str): string {
    $str = mb_strtolower($str);
    $str = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
    $str = preg_replace('/[^a-z0-9]+/', '-', $str);
    return trim($str, '-');
}

function get_groups(): array {
    $pdo = getPDO();
    $stmt = $pdo->query('SELECT * FROM `groups` ORDER BY `id` ASC');
    $groups = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $id = (int)$row['id'];
        $groups[$id] = [
            'name'        => $row['name'],
            'description' => $row['description'],
            'reputation'  => is_numeric($row['reputation']) ? (int)$row['reputation'] : null,
        ];
    }

    return $groups;
}
