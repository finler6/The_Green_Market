<?php
session_start();
require '../backend/db.php';
require '../backend/validation.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token.');
    }

    $email = validateEmail($_POST['email']);
    $password = trim($_POST['password']);

    if ($email && !empty($password)) {
        $query = "SELECT id, name, email, password, role FROM users WHERE email = :email";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];

            // Перенаправление на главную страницу
            header('Location: index.php');
            exit;
        }
    }

    $_SESSION['error'] = "Invalid email or password.";
    header('Location: index.php');
    exit;
}
