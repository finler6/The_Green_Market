<?php
session_start();
require '../backend/db.php';
require '../backend/auth.php';
require '../interface/templates/navigation.php';

ensureRole('moderator');

//generation CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Добавление новой категории
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token.');
    }
    $name = trim($_POST['name']);
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

    if (!empty($name)) {
        $query = "INSERT INTO Categories (name, parent_id) VALUES (:name, :parent_id)";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['name' => $name, 'parent_id' => $parent_id]);
        $success = "Category added successfully!";
    } else {
        $error = "Category name is required.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token.');
    }
    $category_id = (int)$_POST['category_id'];

    $query = "DELETE FROM Categories WHERE id = :id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['id' => $category_id]);
    $success = "Category deleted successfully!";
}

// Получение списка категорий
$query = "SELECT id, name, parent_id FROM Categories";
$categories = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-5">
<?php renderNavigation($_SESSION['user_role']); ?>

<!-- Кнопка возврата на дашборд -->
<div class="mb-4">
    <a href="moderator_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
</div>

<h1>Manage Categories</h1>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Список категорий -->
<h2>Existing Categories</h2>
<table class="table table-hover">
    <thead class="table-dark">
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Parent Category</th>
        <th>Action</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($categories as $category): ?>
        <tr>
            <td><?= htmlspecialchars($category['id']) ?></td>
            <td><?= htmlspecialchars($category['name']) ?></td>
            <td><?= htmlspecialchars($category['parent_id'] ?? 'None') ?></td>
            <td>
                <form method="POST" action="manage_categories.php" class="d-inline">
                    <input type="hidden" name="category_id" value="<?= $category['id'] ?>">
                    <button type="submit" name="delete_category" class="btn btn-danger btn-sm">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>


<!-- Добавление категории -->
<h2 class="mt-4">Add New Category</h2>
<form method="POST" action="manage_categories.php">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <div class="mb-3">
        <label for="name" class="form-label">Category Name</label>
        <input type="text" id="name" name="name" class="form-control" required>
    </div>
    <div class="mb-3">
        <label for="parent_id" class="form-label">Parent Category</label>
        <select id="parent_id" name="parent_id" class="form-select">
            <option value="">None</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" name="add_category" class="btn btn-success">Add Category</button>
</form>
</body>
</html>
