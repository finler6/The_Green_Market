<?php
session_start();
require '../backend/db.php';

// Получение списка категорий для фильтрации
$query = "SELECT id, name FROM Categories";
$categories = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Фильтрация товаров
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;
$where_clause = $category_id ? "WHERE category_id = :category_id" : "";

// Сортировка товаров
$order_by = isset($_GET['order_by']) && in_array($_GET['order_by'], ['price_asc', 'price_desc']) ? $_GET['order_by'] : null;
$order_clause = "";
if ($order_by === 'price_asc') {
    $order_clause = "ORDER BY Products.price ASC";
} elseif ($order_by === 'price_desc') {
    $order_clause = "ORDER BY Products.price DESC";
}

// Формирование запроса
$query = "SELECT Products.id, Products.name, Categories.name AS category, Products.price, Products.quantity, Products.description
          FROM Products
          JOIN Categories ON Products.category_id = Categories.id
          $where_clause
          $order_clause";
$stmt = $pdo->prepare($query);
if ($category_id) {
    $stmt->execute(['category_id' => $category_id]);
} else {
    $stmt->execute();
}
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-5">
<?php if (!empty($_SESSION['user_name'])): ?>
    <div class="text-end mb-3">
        <span>Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?>!</span>
        <?php if ($_SESSION['user_role'] === 'admin'): ?>
            <a href="admin_dashboard.php" class="btn btn-secondary">Admin Dashboard</a>
        <?php elseif ($_SESSION['user_role'] === 'moderator'): ?>
            <a href="moderator_dashboard.php" class="btn btn-secondary">Moderator Dashboard</a>
        <?php elseif ($_SESSION['user_role'] === 'farmer'): ?>
            <a href="farmer_dashboard.php" class="btn btn-secondary">Farmer Dashboard</a>
        <?php elseif ($_SESSION['user_role'] === 'customer'): ?>
            <a href="customer_dashboard.php" class="btn btn-secondary">Customer Dashboard</a>
        <?php endif; ?>
        <a href="logout.php" class="btn btn-danger ms-3">Logout</a>
    </div>
<?php else: ?>
    <div class="text-end mb-3">
        <a href="login.php" class="btn btn-primary">Login</a>
    </div>
<?php endif; ?>
<h1 class="mb-4 text-center">Products</h1>

<!-- Фильтрация по категориям -->
<div class="mb-3">
    <form method="GET" action="index.php" class="d-inline">
    <label for="category_id" class="form-label">Filter by Category:</label>
        <select name="category_id" id="category_id" class="form-select d-inline w-auto">
            <option value="">All Categories</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= $category['id'] ?>" <?= $category_id == $category['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($category['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">Filter</button>
    </form>
</div>

<!-- Список товаров -->
<table class="table table-striped mt-4">
    <thead>
    <tr>
        <th>Name</th>
        <th>Category</th>
        <th>Price</th>
        <th>Available</th>
        <th>Description</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($products as $product): ?>
        <tr>
            <td><?= htmlspecialchars($product['name']) ?></td>
            <td><?= htmlspecialchars($product['category']) ?></td>
            <td><?= htmlspecialchars($product['price']) ?></td>
            <td><?= htmlspecialchars($product['quantity']) ?></td>
            <td><?= htmlspecialchars($product['description']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</body>
</html>
