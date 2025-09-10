<?php # activate.php
require __DIR__ . '/bootstrap.php';

$title = 'Link my account';
add_style('login');

// === Обработка GET-запроса с параметром action (например, ?action=activate) ===
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    // Пока просто заглушка
    header('Content-Type: text/plain');
    echo 'OK, мы на другой ноде и далее должны проверить пароль и выдать событие активации для ноды '.htmlspecialchars($_GET['from']);
    exit;
}

// === Получение origin node из массива пиров или временное переопределение ===
$origin_node = 'http://localhost:8082'; // временно хардкодим

if ($origin_node === '') {
    $message = 'No origin node available for activation.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === '1') {
    // Перенаправление на origin node
    $url = $origin_node . '/activate.php?action=activate&from=localhost8081';
    header('Location: ' . $url);
    exit;
} else {
    // Сообщение по умолчанию
    $message = <<<HTML
<p>It looks like your account was originally created on another part of our forum network.</p>
<p>To use it here, all you need to do is log in once on the original forum where your account was first registered:</p>
<p><strong>$origin_node</strong></p>
<p>After that, you’ll be brought back here automatically, and your account will be ready to use on this forum too.</p>
<p>During this quick activation step, you can keep your current password or choose a new one — whichever you prefer.</p>
<p>This one-time activation helps us keep your account secure across our entire network. Thank you for your understanding!</p>
HTML;
}

// Рендер шаблона
$content = renderTemplate(BASE_ROOT . '/views/activate.tpl.php', [
    'title'      => $title,
    'message'    => $message,
    'csrf_token' => csrf_token(),
]);

draw($title, $content);
