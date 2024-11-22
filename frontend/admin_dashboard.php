<?php
session_start();
require '../backend/auth.php';
require '../backend/db.php';

ensureRole('admin');

// Получение данных для статистики
$total_users = $pdo->query("SELECT COUNT(*) AS total_users FROM Users")->fetchColumn();
$total_products = $pdo->query("SELECT COUNT(*) AS total_products FROM Products")->fetchColumn();
$total_orders = $pdo->query("SELECT COUNT(*) AS total_orders FROM Orders")->fetchColumn();
$total_revenue = $pdo->query("SELECT SUM(total_price) AS total_revenue FROM Orders WHERE status = 'completed'")->fetchColumn();
$pending_categories = $pdo->query("SELECT COUNT(*) AS pending_categories FROM CategoryProposals WHERE status = 'pending'")->fetchColumn();

// Получение данных для графиков
// Заказы по месяцам
$monthly_orders_query = $pdo->query("
    SELECT DATE_FORMAT(order_date, '%Y-%m') AS month, COUNT(*) AS total_orders
    FROM Orders
    GROUP BY month
    ORDER BY month ASC
");
$monthly_orders = $monthly_orders_query->fetchAll(PDO::FETCH_ASSOC);

// Количество пользователей по времени
$users_by_month_query = $pdo->query("
    SELECT FLOOR((id - 1) / 100) AS month_group, COUNT(*) AS total_users
    FROM users
    GROUP BY month_group
    ORDER BY month_group ASC
");
$users_by_month = $users_by_month_query->fetchAll(PDO::FETCH_ASSOC);

// Данные для популярных продуктов
$popular_products_query = $pdo->query("
    SELECT Products.name, COUNT(Orders.id) AS total_orders
    FROM Orders
    JOIN Products ON Orders.product_id = Products.id
    GROUP BY Products.id
    ORDER BY total_orders DESC
    LIMIT 5
");
$popular_products = $popular_products_query->fetchAll(PDO::FETCH_ASSOC);

$title = 'Admin Dashboard';

ob_start();
?>

    <h1 class="text-center mb-4">Admin Dashboard</h1>

    <!-- Вкладки для переключения -->
    <ul class="nav nav-tabs mb-4" id="dashboardTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="stats-tab" data-bs-toggle="tab" data-bs-target="#stats" type="button" role="tab" aria-controls="stats" aria-selected="true">Stats</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="charts-tab" data-bs-toggle="tab" data-bs-target="#charts" type="button" role="tab" aria-controls="charts" aria-selected="false">Charts</button>
        </li>
    </ul>

    <!-- Контент вкладок -->
    <div class="tab-content">
        <!-- Таблица статистики -->
        <div class="tab-pane fade show active" id="stats" role="tabpanel" aria-labelledby="stats-tab">
            <div class="row g-4">
                <div class="col-md-3">
                    <div class="card border-primary text-center shadow">
                        <div class="card-body">
                            <h5 class="card-title">Total Users</h5>
                            <p class="display-6 text-primary"><?= htmlspecialchars($total_users) ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-success text-center shadow">
                        <div class="card-body">
                            <h5 class="card-title">Total Products</h5>
                            <p class="display-6 text-success"><?= htmlspecialchars($total_products) ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-warning text-center shadow">
                        <div class="card-body">
                            <h5 class="card-title">Total Orders</h5>
                            <p class="display-6 text-warning"><?= htmlspecialchars($total_orders) ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-info text-center shadow">
                        <div class="card-body">
                            <h5 class="card-title">Total Revenue</h5>
                            <p class="display-6 text-info">$<?= htmlspecialchars(number_format($total_revenue, 2)) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mt-4">
                <div class="col-md-6">
                    <div class="card border-danger text-center shadow">
                        <div class="card-body">
                            <h5 class="card-title">Pending Categories</h5>
                            <p class="display-6 text-danger"><?= htmlspecialchars($pending_categories) ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow">
                        <div class="card-body">
                            <h5 class="card-title text-center">Top 5 Popular Products</h5>
                            <table class="table table-striped table-hover">
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
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Графики -->
        <div class="tab-pane fade" id="charts" role="tabpanel" aria-labelledby="charts-tab">
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card shadow">
                        <div class="card-body">
                            <h5 class="card-title">Monthly Orders</h5>
                            <canvas id="monthlyOrdersChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card shadow">
                        <div class="card-body">
                            <h5 class="card-title">Users by Month</h5>
                            <canvas id="usersByMonthChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="card shadow">
                        <div class="card-body">
                            <h5 class="card-title">Top 5 Popular Products</h5>
                            <div class="chart-container">
                                <canvas id="popularProductsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Данные для графиков
        const monthlyOrders = <?= json_encode($monthly_orders) ?>;
        const usersByMonth = <?= json_encode($users_by_month) ?>;
        const popularProducts = <?= json_encode($popular_products) ?>;

        // График Monthly Orders
        new Chart(document.getElementById('monthlyOrdersChart'), {
            type: 'line',
            data: {
                labels: monthlyOrders.map(item => item.month),
                datasets: [{
                    label: 'Orders',
                    data: monthlyOrders.map(item => item.total_orders),
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: { title: { display: true, text: 'Month' } },
                    y: { title: { display: true, text: 'Orders' } }
                }
            }
        });

        // График Users by Month
        new Chart(document.getElementById('usersByMonthChart'), {
            type: 'bar',
            data: {
                labels: usersByMonth.map(item => item.month),
                datasets: [{
                    label: 'Users',
                    data: usersByMonth.map(item => item.total_users),
                    backgroundColor: 'rgba(54, 162, 235, 0.6)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: { title: { display: true, text: 'Month' } },
                    y: { title: { display: true, text: 'Users' } }
                }
            }
        });

        // График Top Popular Products
        new Chart(document.getElementById('popularProductsChart'), {
            type: 'doughnut',
            data: {
                labels: popularProducts.map(item => item.name),
                datasets: [{
                    label: 'Orders',
                    data: popularProducts.map(item => item.total_orders),
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.6)',
                        'rgba(54, 162, 235, 0.6)',
                        'rgba(255, 206, 86, 0.6)',
                        'rgba(75, 192, 192, 0.6)',
                        'rgba(153, 102, 255, 0.6)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top' },
                }
            }
        });
    </script>

<?php
$content = ob_get_clean();
require '../interface/templates/layout.php';
