<?php
session_start();
require '../backend/db.php';
require 'navigation.php';

// Проверка роли клиента
if ($_SESSION['user_role'] !== 'customer') {
    header('Location: login.php');
    exit;
}

// Добавление товара в корзину
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $product_id = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];

    if ($product_id > 0 && $quantity > 0) {
        // Сохраняем данные в сессию (упрощённый вариант корзины)
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id] += $quantity;
        } else {
            $_SESSION['cart'][$product_id] = $quantity;
        }
        header('Location: customer_dashboard.php');
        exit;
    }
}

// Получение списка товаров
$query = "SELECT Products.id, Products.name, Categories.name AS category, Products.price, Products.quantity, Products.description
          FROM Products
          JOIN Categories ON Products.category_id = Categories.id";
$products = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-5">
<?php renderNavigation($_SESSION['user_role']); ?>
<h1>Browse Products</h1>

<!-- Список товаров -->
<table class="table table-striped mt-4">
    <thead>
    <tr>
        <th>Name</th>
        <th>Category</th>
        <th>Price</th>
        <th>Available</th>
        <th>Description</th>
        <th>Action</th>
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
            <td>
                <?php if ($product['quantity'] > 0): ?>
                    <form method="POST" action="customer_dashboard.php" class="d-inline">
                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                        <input type="number" name="quantity" class="form-control d-inline w-25" min="1" max="<?= $product['quantity'] ?>" required>
                        <button type="submit" name="add_to_cart" class="btn btn-success btn-sm">Add to Cart</button>
                    </form>
                <?php else: ?>
                    <span class="text-danger">Out of stock</span>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</body>
</html>
