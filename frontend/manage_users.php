<?php
session_start();
require '../backend/db.php';

// Проверка роли администратора
if (empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Обработка изменения роли пользователя
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'];
    $new_role = $_POST['role'];

    $query = "UPDATE Users SET role = :role WHERE id = :id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['role' => $new_role, 'id' => $user_id]);
    header('Location: manage_users.php');
    exit;
}

// Получение списка пользователей
$query = "SELECT id, name, email, role FROM Users";
$stmt = $pdo->query($query);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-5">
<h1>Manage Users</h1>
<table class="table table-striped">
    <thead>
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Email</th>
        <th>Role</th>
        <th>Action</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($users as $user): ?>
        <tr>
            <td><?= htmlspecialchars($user['id']) ?></td>
            <td><?= htmlspecialchars($user['name']) ?></td>
            <td><?= htmlspecialchars($user['email']) ?></td>
            <td><?= htmlspecialchars($user['role']) ?></td>
            <td>
                <form method="POST" action="manage_users.php" class="d-inline">
                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                    <select name="role" class="form-select d-inline w-auto">
                        <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="moderator" <?= $user['role'] === 'moderator' ? 'selected' : '' ?>>Moderator</option>
                        <option value="farmer" <?= $user['role'] === 'farmer' ? 'selected' : '' ?>>Farmer</option>
                        <option value="customer" <?= $user['role'] === 'customer' ? 'selected' : '' ?>>Customer</option>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">Update</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<a href="admin_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
</body>
</html>
