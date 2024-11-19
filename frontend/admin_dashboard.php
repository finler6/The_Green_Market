<?php
session_start();
require '../backend/db.php';
require 'navigation.php';

// Проверка роли администратора
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-5">
<?php renderNavigation($_SESSION['user_role']); ?>
<h1>Welcome to Admin Dashboard, <?= htmlspecialchars($_SESSION['user_name']) ?>!</h1>
<p>Use the navigation above to access admin functions.</p>
</body>
</html>
