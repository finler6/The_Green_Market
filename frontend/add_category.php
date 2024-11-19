<?php
require '../backend/db.php';

// Обработка данных формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

    if (!empty($name)) {
        // Вставляем новую категорию
        $query = "INSERT INTO Categories (name, parent_id) VALUES (:name, :parent_id)";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['name' => $name, 'parent_id' => $parent_id]);

        // Перенаправление обратно на страницу категорий
        header('Location: index.php');
        exit;
    } else {
        $error = "Category name is required!";
    }
}

// Получение родительских категорий
$query = "SELECT id, name FROM Categories";
$stmt = $pdo->query($query);
$parent_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Category</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-5">
<h1 class="mb-4">Add New Category</h1>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<form method="POST" action="add_category.php">
    <div class="mb-3">
        <label for="name" class="form-label">Category Name</label>
        <input type="text" class="form-control" id="name" name="name" required>
    </div>
    <div class="mb-3">
        <label for="parent_id" class="form-label">Parent Category (optional)</label>
        <select class="form-control" id="parent_id" name="parent_id">
            <option value="">No Parent</option>
            <?php foreach ($parent_categories as $parent): ?>
                <option value="<?= $parent['id'] ?>"><?= htmlspecialchars($parent['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn btn-success">Add Category</button>
    <a href="index.php" class="btn btn-secondary">Cancel</a>
</form>
</body>
</html>
