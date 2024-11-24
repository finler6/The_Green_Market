<?php
session_start(); // Начало сессии для сохранения данных о пользователе

require '../backend/db.php';
require '../backend/validation.php';

//generation CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token.');
    }
    $email = validateEmail($_POST['email']);
    $password = trim($_POST['password']);

    if (!$email || empty($password)) {
        $error = "Invalid email or password.";
    }

    // Проверка на пустые поля
    if (empty($email) || empty($password)) {
        $error = "Both fields are required.";
    } else {
        $query = "SELECT id, name, email, password, role FROM Users WHERE email = :email";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['email' => htmlspecialchars($email)]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Если пользователь найден и пароль верный
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];

            // Перенаправление в зависимости от роли
            switch ($_SESSION['user_role']) {
                case 'admin':
                    header('Location: index.php');
                    break;
                case 'moderator':
                    header('Location: index.php');
                    break;
                case 'farmer':
                    header('Location: index.php');
                    break;
                case 'customer':
                    header('Location: index.php');
                    break;
                default:
                    header('Location: index.php');
            }
            exit;
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-5">
<div class="text-end mb-3">
    <a href="../index.php" class="btn btn-primary">Back to Main Page</a>
</div>
<h1 class="mb-4">Login</h1>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<form method="POST" action="login.php">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <div class="mb-3">
        <label for="email" class="form-label">Email</label>
        <input type="email" class="form-control" id="email" name="email" required>
    </div>
    <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input type="password" class="form-control" id="password" name="password" required>
    </div>
    <button type="submit" class="btn btn-success">Login</button>
    <a href="../register.php" class="btn btn-secondary">Register</a>
</form>
</body>
</html>
