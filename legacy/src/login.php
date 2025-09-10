<?php # login.php
require __DIR__ . '/bootstrap.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $csrf_token = $_POST['csrf_token'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please fill in both fields.';
    } elseif (strlen($username) > 50 || strlen($password) > 128) {
        $error = 'Input too long.';
    } else {
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare('SELECT id, username, password FROM users WHERE username = :username LIMIT 1');
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                if ($user['password'] === '') {
                    // Импортированный аккаунт — показываем инфо-окно
                    $data = [
                        'title'    => 'Link my account',
                        'uuid' => htmlspecialchars($user['id']),
                        'username' => htmlspecialchars($user['username']),
                        'message'  => '<b>Your account was originally created on another forum within our connected network.</b>
<p style="white-space: pre-wrap;word-wrap: break-word;overflow-wrap: break-word;text-align: justify;">
To use your account here, a quick one-time activation is needed.
Just click the button below — you will be taken to the forum where you first registered. There, you will log in to confirm it is really you.
After that, you will be automatically returned here, and everything will work as usual.

This extra step helps protect your account and ensures that only you can activate it.
We apologize for the inconvenience and thank you for your understanding.</p>',
                        'csrf_token' => csrf_token(),
                    ];
                    add_style('login');
                    $content = renderTemplate(HTML_ROOT . '/views/info.tpl.php', $data);
                    draw($data['title'], $content);
                    exit;
                } elseif (password_verify($password, $user['password'])) {
                    $_SESSION['user'] = [
                        'id'   => $user['id'],
                        'name' => $user['username'],
                    ];
                    header('Location: /');
                    exit;
                } else {
                    $error = 'Invalid credentials.';
                }
            } else {
                $error = 'Invalid credentials.';
            }
        } catch (Throwable $e) {
            $error = 'Login error occurred. Please try again later.';
        }
    }
}

$data = [
    'title'      => 'Login',
    'error'      => $error,
    'csrf_token' => csrf_token(),
    'username'   => $_POST['username'] ?? '',
];

add_style('login');
$content = renderTemplate(HTML_ROOT . '/views/login.tpl.php', $data);
draw($data['title'], $content);
