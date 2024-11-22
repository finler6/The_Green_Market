<?php
session_start();
require '../backend/db.php';
require '../interface/templates/navigation.php';

// Проверка роли фермера
if ($_SESSION['user_role'] !== 'farmer') {
    header('Location: login.php');
    exit;
}

$farmer_id = $_SESSION['user_id'];

// Добавление товара
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name = htmlspecialchars(trim($_POST['name']));
    $category_id = (int)$_POST['category_id'];
    $price = (float)$_POST['price'];
    $quantity = (int)$_POST['quantity'];
    $description = htmlspecialchars(trim($_POST['description']));

    if (!empty($name) && $category_id && $price > 0 && $quantity >= 0) {
        $query = "INSERT INTO Products (name, farmer_id, category_id, price, quantity, description)
                  VALUES (:name, :farmer_id, :category_id, :price, :quantity, :description)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            'name' => $name,
            'farmer_id' => $_SESSION['user_id'],
            'category_id' => $category_id,
            'price' => $price,
            'quantity' => $quantity,
            'description' => $description
        ]);
    }
}

// Получение списка товаров фермера
$query = "SELECT Products.id, Products.name, Categories.name AS category, Products.price, Products.quantity, Products.description
          FROM Products
          JOIN Categories ON Products.category_id = Categories.id
          WHERE Products.farmer_id = :farmer_id";
$stmt = $pdo->prepare($query);
$stmt->execute(['farmer_id' => $farmer_id]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получение категорий для добавления товара
$query = "SELECT id, name FROM Categories";
$categories = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-5">
<?php renderNavigation($_SESSION['user_role']); ?>
<h1>Manage Your Products</h1>

<!-- Список товаров -->
<h2 class="mt-4">Your Products</h2>
<table class="table table-striped">
    <thead>
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Category</th>
        <th>Price</th>
        <th>Quantity</th>
        <th>Description</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($products as $product): ?>
        <tr>
            <td><?= htmlspecialchars($product['id']) ?></td>
            <td><?= htmlspecialchars($product['name']) ?></td>
            <td><?= htmlspecialchars($product['category']) ?></td>
            <td><?= htmlspecialchars($product['price']) ?></td>
            <td><?= htmlspecialchars($product['quantity']) ?></td>
            <td><?= htmlspecialchars($product['description']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<!-- Добавление товара -->
<h2 class="mt-4">Add New Product</h2>
<form method="POST" action="farmer_dashboard.php">
    <div class="mb-3">
        <label for="name" class="form-label">Product Name</label>
        <input type="text" id="name" name="name" class="form-control" required>
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
    <div class="mb-3">
        <label for="price" class="form-label">Price</label>
        <input type="number" step="0.01" id="price" name="price" class="form-control" required>
    </div>
    <div class="mb-3">
        <label for="quantity" class="form-label">Quantity</label>
        <input type="number" id="quantity" name="quantity" class="form-control" required>
    </div>
    <div class="mb-3">
        <label for="description" class="form-label">Description</label>
        <textarea id="description" name="description" class="form-control" rows="3"></textarea>
    </div>
    <button type="submit" name="add_product" class="btn btn-success">Add Product</button>
</form>
</body>
</html>
