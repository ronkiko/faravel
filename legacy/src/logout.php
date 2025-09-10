<?php # logout.php
require __DIR__ . '/bootstrap.php';

// Удаляем данные пользователя из сессии
unset($_SESSION['user']);

// Также можно очистить всю сессию, если нужно
session_unset();
session_destroy();

// Перенаправляем на главную
header('Location: /');
exit;
