<?php # register.php
require __DIR__ . '/bootstrap.php';

$error = '';
$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm'] ?? '');

    if ($username === '' || $password === '' || $confirm === '') {
        $error = 'Please fill in all fields.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        $error = 'Username must be 3–30 characters long and contain only letters, numbers, and underscores.';
    } elseif (strlen($password) < 8 || strlen($password) > 100) {
        $error = 'Password must be 8–100 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
            $stmt->execute([$username]);

            if ($stmt->fetchColumn() > 0) {
                $error = 'Username is already taken.';
            } else {
                $uuid       = generateUUID();
                $registered = floor(time() / 86400);
                $hash       = password_hash($password, PASSWORD_BCRYPT);

                // Логируем событие UserCreated
                logEvent('UserCreated', [
                    'id'         => $uuid,
                    'name'       => $username,
                    'password'   => $hash,
                    'registered' => $registered
                ]);

                // Записываем пользователя в базу
                $stmt = $pdo->prepare('
                    INSERT INTO users (id, username, password, registered, role_id, language)
                    VALUES (:id, :username, :password, :registered, :role_id, :language)
                ');
                $stmt->execute([
                    ':id'         => $uuid,
                    ':username'   => $username,
                    ':password'   => $hash,
                    ':registered' => $registered,
                    ':role_id'    => 1,
                    ':language'   => 1
                ]);

                $_SESSION['user'] = [
                    'id'   => $uuid,
                    'name' => $username,
                ];

                header('Location: /');
                exit;
            }
        } catch (Throwable $e) {
            error_log('[REGISTRATION ERROR] ' . $e->getMessage());
            $error = 'Registration failed.';
        }
    }
}

$data = [
    'title'      => 'Register',
    'error'      => $error,
    'csrf_token' => $_SESSION['csrf_token'],
    'username'   => $_POST['username'] ?? '',
];

add_style('login');

$content = renderTemplate(HTML_ROOT . '/views/register.tpl.php', $data);
draw('Register', $content);
