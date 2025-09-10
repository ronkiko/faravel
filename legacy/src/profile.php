<?php
require __DIR__ . '/bootstrap.php';

if (!IS_LOGGED_IN) {
    http_response_code(403);
    exit('Forbidden: Not logged in');
}

$pdo = getPDO();

// Получение полной информации о пользователе
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.registered, u.reputation, u.language, u.title,
           u.style, u.signature, u.last_visit, u.last_post, u.last_updated,
           u.role_id, u.group_id,
           r.name AS role_name, r.label AS role_label, r.description AS role_description,
           g.name AS group_name, g.description AS group_description
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    LEFT JOIN groups g ON u.group_id = g.id
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user']['id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404);
    exit('User not found');
}

// Преобразование дней (с 1970 года) в дату
function daystamp_to_date(?int $days): string {
    if (!$days) return '—';
    return date('Y-m-d', $days * 86400);
}

// Подготовка данных для шаблона
$data = [
    'title'              => 'My Profile',
    'uuid'               => $user['id'],
    'username'           => $user['username'],
    'role_id'            => $user['role_id'],
    'role_name'          => $user['role_name'] ?? '—',
    'role_label'         => $user['role_label'] ?? '',
    'role_description'   => $user['role_description'] ?? '',
    'group_id'           => $user['group_id'],
    'group_name'         => $user['group_name'] ?? '—',
    'group_description'  => $user['group_description'] ?? '',
    'reputation'         => $user['reputation'],
    'registered_date'    => daystamp_to_date((int)$user['registered']),
    'last_visit_date'    => daystamp_to_date((int)$user['last_visit']),
    'last_post_date'     => daystamp_to_date((int)$user['last_post']),
    'language'           => $user['language'],
    'title_text'         => $user['title'] ?: '—',
    'style_name'         => '—', // Пока не реализовано
    'signature_html'     => nl2br(htmlspecialchars($user['signature'] ?? '')),
    'csrf_token'         => csrf_token(),
];

add_style('profile');
$content = renderTemplate(HTML_ROOT . '/views/profile.tpl.php', $data);
draw('Profile', $content);
