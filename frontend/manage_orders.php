<?php
session_start();
require '../backend/db.php';
require 'navigation.php';

// Проверка роли фермера
if ($_SESSION['user_role'] !== 'farmer') {
    header('Location: login.php');
    exit;
}

// Получение заказов, связанных с товарами фермера
$query = "SELECT Orders.id AS order_id, Users.name AS customer_name, Products.name AS product_name,
                 Orders.quantity, Orders.total_price, Orders.status, Orders.order_date
          FROM Orders
          JOIN Products ON Orders.product_id = Products.id
          JOIN Users ON Orders.customer_id = Users.id
          WHERE Products.farmer_id = :farmer_id
          ORDER BY Orders.order_date DESC";
$stmt = $pdo->prepare($query);
$stmt->execute(['farmer_id' => $_SESSION['user_id']]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Обработка изменения статуса заказа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['action'])) {
    $order_id = (int)$_POST['order_id'];
    $action = $_POST['action'];

    if (in_array($action, ['completed', 'cancelled'])) {
        $query = "UPDATE Orders SET status = :status WHERE id = :order_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['status' => $action, 'order_id' => $order_id]);
        header('Location: manage_orders.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-5">
<?php renderNavigation($_SESSION['user_role']); ?>
<h1>Manage Orders</h1>

<table class="table table-striped">
    <thead>
    <tr>
        <th>Order ID</th>
        <th>Customer</th>
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
            <td><?= htmlspecialchars($order['customer_name']) ?></td>
            <td><?= htmlspecialchars($order['product_name']) ?></td>
            <td><?= htmlspecialchars($order['quantity']) ?></td>
            <td><?= htmlspecialchars($order['total_price']) ?></td>
            <td><?= htmlspecialchars($order['status']) ?></td>
            <td><?= htmlspecialchars($order['order_date']) ?></td>
            <td>
                <?php if ($order['status'] === 'pending'): ?>
                    <form method="POST" action="manage_orders.php" class="d-inline">
                        <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                        <button type="submit" name="action" value="completed" class="btn btn-success btn-sm">Complete</button>
                        <button type="submit" name="action" value="cancelled" class="btn btn-danger btn-sm">Cancel</button>
                    </form>
                <?php else: ?>
                    <?= htmlspecialchars(ucfirst($order['status'])) ?>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</body>
</html>
