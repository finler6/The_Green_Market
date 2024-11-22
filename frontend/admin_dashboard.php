<?php
session_start();
require '../backend/auth.php';
require '../backend/db.php';
require '../interface/templates/navigation.php';

ensureRole('admin');

// Получение данных для статистики
$total_users = $pdo->query("SELECT COUNT(*) AS total_users FROM Users")->fetchColumn();
$total_products = $pdo->query("SELECT COUNT(*) AS total_products FROM Products")->fetchColumn();
$total_orders = $pdo->query("SELECT COUNT(*) AS total_orders FROM Orders")->fetchColumn();
$total_revenue = $pdo->query("SELECT SUM(total_price) AS total_revenue FROM Orders WHERE status = 'completed'")->fetchColumn();
$pending_categories = $pdo->query("SELECT COUNT(*) AS pending_categories FROM CategoryProposals WHERE status = 'pending'")->fetchColumn();

$popular_products_query = $pdo->query("
    SELECT Products.name, COUNT(Orders.id) AS total_orders
    FROM Orders
    JOIN Products ON Orders.product_id = Products.id
    GROUP BY Products.id
    ORDER BY total_orders DESC
    LIMIT 5
");
$popular_products = $popular_products_query->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-5">
<?php renderNavigation($_SESSION['user_role']); ?>

<h1>Admin Dashboard</h1>

<div class="row">
    <div class="col-md-4">
        <div class="card text-white bg-primary mb-3">
            <div class="card-body">
                <h5 class="card-title">Total Users</h5>
                <p class="card-text"><?= htmlspecialchars($total_users) ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-success mb-3">
            <div class="card-body">
                <h5 class="card-title">Total Products</h5>
                <p class="card-text"><?= htmlspecialchars($total_products) ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-warning mb-3">
            <div class="card-body">
                <h5 class="card-title">Total Orders</h5>
                <p class="card-text"><?= htmlspecialchars($total_orders) ?></p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card text-white bg-info mb-3">
            <div class="card-body">
                <h5 class="card-title">Total Revenue</h5>
                <p class="card-text">$<?= htmlspecialchars(number_format($total_revenue, 2)) ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card text-white bg-danger mb-3">
            <div class="card-body">
                <h5 class="card-title">Pending Categories</h5>
                <p class="card-text"><?= htmlspecialchars($pending_categories) ?></p>
            </div>
        </div>
    </div>
</div>

<h2>Top 5 Popular Products</h2>
<table class="table table-striped">
    <thead>
    <tr>
        <th>Product Name</th>
        <th>Total Orders</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($popular_products as $product): ?>
        <tr>
            <td><?= htmlspecialchars($product['name']) ?></td>
            <td><?= htmlspecialchars($product['total_orders']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
