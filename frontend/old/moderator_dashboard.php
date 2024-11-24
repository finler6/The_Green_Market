<?php
session_start();
require '../backend/db.php';
require '../backend/auth.php';
require '../interface/templates/navigation.php';

ensureRole('moderator');

// Добавление категории
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['name']);
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

    if (!empty($name)) {
        $query = "INSERT INTO Categories (name, parent_id) VALUES (:name, :parent_id)";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['name' => $name, 'parent_id' => $parent_id]);
        header('Location: moderator_dashboard.php');
        exit;
    }
}

// Получение категорий
$query = "SELECT id, name, parent_id FROM Categories";
$stmt = $pdo->query($query);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderator Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-5">
<?php renderNavigation($_SESSION['user_role']); ?>
<h1>Moderator Dashboard</h1>

<!-- Ссылки на управление категориями и атрибутами -->
<div class="mb-4">
    <a href="../manage_categories.php" class="btn btn-primary">Manage Categories</a>
    <a href="../moderate_categories.php" class="btn btn-primary">Moderate Category Proposals</a>
    <a href="../manage_attributes.php" class="btn btn-secondary">Manage Attributes</a>
</div>

<!-- Управление категориями прямо из дашборда -->
<h2>Manage Categories</h2>
<table class="table table-striped">
    <thead>
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Parent ID</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($categories as $category): ?>
        <tr>
            <td><?= htmlspecialchars($category['id']) ?></td>
            <td><?= htmlspecialchars($category['name']) ?></td>
            <td><?= htmlspecialchars(isset($category['parent_id']) ? $category['parent_id'] : 'None') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<!-- Форма добавления категории -->
<h3>Add New Category</h3>
<form method="POST" action="moderator_dashboard.php">
    <div class="mb-3">
        <label for="name" class="form-label">Category Name</label>
        <input type="text" name="name" id="name" class="form-control" required>
    </div>
    <div class="mb-3">
        <label for="parent_id" class="form-label">Parent Category</label>
        <select name="parent_id" id="parent_id" class="form-select">
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

