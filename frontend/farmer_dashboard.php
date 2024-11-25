<?php
session_start();
require '../backend/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'farmer') {
    header('Location: login.php');
    exit;
}

$current_user_id = $_SESSION['user_id'];

$completed_orders_query = "
    SELECT COUNT(DISTINCT orders.id) AS total_completed_orders, 
           SUM(orderitems.total_price) AS total_revenue,
           SUM(orderitems.quantity) AS total_items_sold
    FROM orders
    JOIN orderitems ON orders.id = orderitems.order_id
    JOIN products ON orderitems.product_id = products.id
    WHERE products.farmer_id = :farmer_id AND orders.status = 'completed'
";
$stmt = $pdo->prepare($completed_orders_query);
$stmt->execute(['farmer_id' => $current_user_id]);
$completed_orders_stats = $stmt->fetch(PDO::FETCH_ASSOC);

$pending_orders_query = "
    SELECT COUNT(DISTINCT orders.id) AS total_pending_orders, 
           SUM(orderitems.total_price) AS pending_revenue,
           SUM(orderitems.quantity) AS pending_items
    FROM orders
    JOIN orderitems ON orders.id = orderitems.order_id
    JOIN products ON orderitems.product_id = products.id
    WHERE products.farmer_id = :farmer_id AND orders.status = 'pending'
";
$stmt = $pdo->prepare($pending_orders_query);
$stmt->execute(['farmer_id' => $current_user_id]);
$pending_orders_stats = $stmt->fetch(PDO::FETCH_ASSOC);

$product_revenue_query = "
    SELECT products.name AS product_name, 
           SUM(orderitems.quantity) AS total_quantity_sold, 
           SUM(orderitems.total_price) AS total_revenue
    FROM orderitems
    JOIN products ON orderitems.product_id = products.id
    JOIN orders ON orders.id = orderitems.order_id
    WHERE products.farmer_id = :farmer_id AND orders.status = 'completed'
    GROUP BY products.name
";
$stmt = $pdo->prepare($product_revenue_query);
$stmt->execute(['farmer_id' => $current_user_id]);
$product_revenue_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

$product_names = array_column($product_revenue_stats, 'product_name');
$product_revenues = array_column($product_revenue_stats, 'total_revenue');
$product_quantities = array_column($product_revenue_stats, 'total_quantity_sold');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$title = 'Farmer Dashboard';
ob_start();
?>

<div class="container mt-5">
    <h1>Farmer Dashboard</h1>
    <div class="row">
        <div class="col-md-4">
            <div class="card bg-success text-white mb-3">
                <div class="card-body">
                    <h5 class="card-title">Completed Orders</h5>
                    <p class="card-text">
                        <?= htmlspecialchars($completed_orders_stats['total_completed_orders'] ?? 0) ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-primary text-white mb-3">
                <div class="card-body">
                    <h5 class="card-title">Total Revenue</h5>
                    <p class="card-text">
                        $<?= number_format($completed_orders_stats['total_revenue'] ?? 0, 2) ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning text-dark mb-3">
                <div class="card-body">
                    <h5 class="card-title">Pending Orders</h5>
                    <p class="card-text">
                        <?= htmlspecialchars($pending_orders_stats['total_pending_orders'] ?? 0) ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($product_revenues)): ?>
        <div class="row mt-4">
            <div class="col-md-6">
                <h3>Revenue by Product</h3>
                <canvas id="productRevenueChart" style="max-width: 400px; max-height: 400px;"></canvas>
            </div>
            <div class="col-md-6">
                <h3>Quantity Sold by Product</h3>
                <canvas id="productQuantityChart" style="max-width: 400px; max-height: 400px;"></canvas>
            </div>
        </div>
    <?php else: ?>
        <div class="row mt-4">
            <div class="col-md-12">
                <p class="text-center text-muted">No revenue data available for your products.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    function initProductCharts() {
        document.addEventListener('DOMContentLoaded', function () {
            const productNames = <?= json_encode($product_names) ?>;
            const productRevenues = <?= json_encode($product_revenues) ?>;
            const productQuantities = <?= json_encode($product_quantities) ?>;

            if (productNames.length > 0 && productRevenues.length > 0) {
                const revenueCtx = document.getElementById('productRevenueChart').getContext('2d');
                new Chart(revenueCtx, {
                    type: 'doughnut',
                    data: {
                        labels: productNames,
                        datasets: [{
                            label: 'Revenue',
                            data: productRevenues,
                            backgroundColor: [
                                'rgba(255, 99, 132, 0.6)',
                                'rgba(54, 162, 235, 0.6)',
                                'rgba(255, 206, 86, 0.6)',
                                'rgba(75, 192, 192, 0.6)',
                                'rgba(153, 102, 255, 0.6)',
                                'rgba(255, 159, 64, 0.6)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                            }
                        }
                    }
                });
            }

            if (productNames.length > 0 && productQuantities.length > 0) {
                const quantityCtx = document.getElementById('productQuantityChart').getContext('2d');
                new Chart(quantityCtx, {
                    type: 'doughnut',
                    data: {
                        labels: productNames,
                        datasets: [{
                            label: 'Quantity Sold',
                            data: productQuantities,
                            backgroundColor: [
                                'rgba(255, 159, 64, 0.6)',
                                'rgba(153, 102, 255, 0.6)',
                                'rgba(75, 192, 192, 0.6)',
                                'rgba(255, 206, 86, 0.6)',
                                'rgba(54, 162, 235, 0.6)',
                                'rgba(255, 99, 132, 0.6)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                            }
                        }
                    }
                });
            }
        });
    }

    initProductCharts();
</script>
<?php
$content = ob_get_clean();
require '../interface/templates/layout.php';
?>
