<?php
session_start();
require '../backend/db.php';
require '../interface/templates/navigation.php';
require '../backend/auth.php';

ensureRole('moderator');

// Генерация CSRF токена
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Добавление нового атрибута
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_attribute'])) {
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token.');
    }
    $name = trim($_POST['name']);
    $type = $_POST['type'];
    $category_id = (int)$_POST['category_id'];

    if (!empty($name) && !empty($type) && $category_id > 0) {
        $query = "INSERT INTO Attributes (name, type, category_id) VALUES (:name, :type, :category_id)";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['name' => $name, 'type' => $type, 'category_id' => $category_id]);
        $success = "Attribute added successfully!";
    } else {
        $error = "All fields are required.";
    }
}

// Удаление атрибута
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_attribute'])) {
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token.');
    }
    $attribute_id = (int)$_POST['attribute_id'];

    $query = "DELETE FROM Attributes WHERE id = :id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['id' => $attribute_id]);
    $success = "Attribute deleted successfully!";
}

// Получение атрибутов
$query = "SELECT Attributes.id, Attributes.name, Attributes.type, Categories.name AS category_name
          FROM Attributes
          JOIN Categories ON Attributes.category_id = Categories.id";
$attributes = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Получение категорий
$query = "SELECT id, name FROM Categories";
$categories = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Attributes</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-5">
<?php renderNavigation($_SESSION['user_role']); ?>

<!-- Кнопка возврата на дашборд -->
<div class="mb-4">
    <a href="moderator_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
</div>

<h1>Manage Attributes</h1>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Список атрибутов -->
<h2>Existing Attributes</h2>
<table class="table table-striped">
    <thead>
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Type</th>
        <th>Category</th>
        <th>Action</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($attributes as $attribute): ?>
        <tr>
            <td><?= htmlspecialchars($attribute['id']) ?></td>
            <td><?= htmlspecialchars($attribute['name']) ?></td>
            <td><?= htmlspecialchars($attribute['type']) ?></td>
            <td><?= htmlspecialchars($attribute['category_name']) ?></td>
            <td>
                <form method="POST" action="manage_attributes.php" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="attribute_id" value="<?= $attribute['id'] ?>">
                    <button type="submit" name="delete_attribute" class="btn btn-danger btn-sm">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<!-- Добавление атрибута -->
<h2 class="mt-4">Add New Attribute</h2>
<form method="POST" action="manage_attributes.php">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <div class="mb-3">
        <label for="name" class="form-label">Attribute Name</label>
        <input type="text" id="name" name="name" class="form-control" required>
    </div>
    <div class="mb-3">
        <label for="type" class="form-label">Type</label>
        <select id="type" name="type" class="form-select" required>
            <option value="text">Text</option>
            <option value="number">Number</option>
            <option value="date">Date</option>
        </select>
    </div>
    <div class="mb-3">
        <label for="category_id" class="form-label">Category</label>
        <select id="category_id" name="category_id" class="form-select" required>
            <option value="">Select a category</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" name="add_attribute" class="btn btn-success">Add Attribute</button>
</form>
</body>
</html>
