<?php
session_start();
require '../backend/db.php';
require '../backend/validation.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token.');
    }
    $name = validateString($_POST['name']);
    $email = validateEmail($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    if (empty($name)) {
        $errors[] = "Name is required.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $role = 'customer';

        try {
            $query = "INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, :role)";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                'name' => $name,
                'email' => $email,
                'password' => $hashed_password,
                'role' => $role
            ]);
            $_SESSION['success'] = "Registration successful! You can now log in.";
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $errors[] = "Email already exists.";
            } else {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }

    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data'] = ['name' => $name, 'email' => $email];
    header('Location: index.php');
    exit;
}
?>
