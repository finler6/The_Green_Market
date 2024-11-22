<?php
session_start();
require '../backend/db.php';
require '../interface/templates/navigation.php';

// Проверка роли клиента
if ($_SESSION['user_role'] !== 'customer') {
    header('Location: login.php');
    exit;
}

// Получение заказов клиента
$query = "SELECT Orders.id AS order_id, Products.id AS product_id, Products.name AS product_name,
                 Orders.quantity, Orders.total_price, Orders.status, Orders.order_date
          FROM Orders
          JOIN Products ON Orders.product_id = Products.id
          WHERE Orders.customer_id = :customer_id
          ORDER BY Orders.order_date DESC";
$stmt = $pdo->prepare($query);
$stmt->execute(['customer_id' => $_SESSION['user_id']]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-5">
<?php renderNavigation($_SESSION['user_role']); ?>
<h1>My Orders</h1>

<table class="table table-striped">
    <thead>
    <tr>
        <th>Order ID</th>
        <th>Product</th>
        <th>Quantity</th>
        <th>Total Price</th>
        <th>Status</th>
        <th>Order Date</th>
        <th>Action</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($orders as $order): ?>
        <tr>
            <td><?= htmlspecialchars($order['order_id']) ?></td>
            <td><?= htmlspecialchars($order['product_name']) ?></td>
            <td><?= htmlspecialchars($order['quantity']) ?></td>
            <td><?= htmlspecialchars($order['total_price']) ?></td>
            <td><?= htmlspecialchars($order['status']) ?></td>
            <td><?= htmlspecialchars($order['order_date']) ?></td>
            <td>
                <?php if ($order['status'] === 'completed'): ?>
                    <a href="add_review.php?product_id=<?= $order['product_id'] ?>" class="btn btn-primary btn-sm">Leave a Review</a>
                <?php else: ?>
                    <span class="text-muted">No actions available</span>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</body>
</html>
