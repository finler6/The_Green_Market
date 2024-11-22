<?php
session_start();
require '../backend/auth.php';
require '../backend/db.php';

ensureRole('admin');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

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

// Обновление роли пользователя
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token.');
    }

    $user_id = (int)$_POST['user_id'];
    $new_role = htmlspecialchars($_POST['role']);

    $query = "UPDATE Users SET role = :role WHERE id = :id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['role' => $new_role, 'id' => $user_id]);
}

// Удаление пользователя
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token.');
    }

    $user_id = (int)$_POST['user_id'];
    $query = "DELETE FROM Users WHERE id = :id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['id' => $user_id]);
}

// Создание нового пользователя
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token.');
    }
    $name = htmlspecialchars($_POST['name']);
    $email = htmlspecialchars($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    $query = "INSERT INTO Users (name, email, password, role) VALUES (:name, :email, :password, :role)";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['name' => $name, 'email' => $email, 'password' => $password, 'role' => $role]);

    // Перенаправление после успешного создания
    header('Location: admin_dashboard.php?tab=users');
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['query'])) {
    $query = trim(htmlspecialchars($_GET['query']));
    $search_query = "%$query%";
    $search_users_query = $pdo->prepare("
        SELECT id, name, email, role 
        FROM Users 
        WHERE name LIKE :query OR email LIKE :query
    ");
    $search_users_query->execute(['query' => $search_query]);

    $search_users = $search_users_query->fetchAll(PDO::FETCH_ASSOC);

    // Отладочный вывод (удалите в продакшене)
    if (empty($search_users)) {
        error_log("No users found for query: $query");
    }

    header('Content-Type: application/json');
    echo json_encode($search_users);
    exit;
}

// Получение списка пользователей
$query = "SELECT id, name, email, role FROM Users";
$stmt = $pdo->query($query);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button" role="tab" aria-controls="users" aria-selected="false">Users</button>
        </li>
    </ul>

    <!-- Контент вкладок -->
    <div class="tab-content">
        <!-- Вкладка с пользователями -->
        <div class="tab-pane fade show active" id="users" role="tabpanel" aria-labelledby="users-tab">
            <ul class="nav nav-tabs mb-4" id="userTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="user-list-tab" data-bs-toggle="tab" data-bs-target="#user-list" type="button" role="tab" aria-controls="user-list" aria-selected="true">User List</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="create-user-tab" data-bs-toggle="tab" data-bs-target="#create-user" type="button" role="tab" aria-controls="create-user" aria-selected="false">Create User</button>
                </li>
            </ul>

            <div class="tab-content">
                <!-- Таблица пользователей -->
                <div class="tab-pane fade show active" id="user-list" role="tabpanel" aria-labelledby="user-list-tab">
                    <div class="mb-4">
                        <input type="text" id="searchUsers" class="form-control" placeholder="Search by email or name...">
                    </div>
                    <table class="table table-striped">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                            <!-- Данные будут загружаться динамически -->
                        </tbody>
                    </table>
                </div>

                <!-- Создание нового пользователя -->
                <div class="tab-pane fade" id="create-user" role="tabpanel" aria-labelledby="create-user-tab">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <div class="mb-3">
                            <label for="userName" class="form-label">Name</label>
                            <input type="text" id="userName" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="userEmail" class="form-label">Email</label>
                            <input type="email" id="userEmail" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="userPassword" class="form-label">Password</label>
                            <input type="password" id="userPassword" name="password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="userRole" class="form-label">Role</label>
                            <select id="userRole" name="role" class="form-select">
                                <option value="customer">Customer</option>
                                <option value="farmer">Farmer</option>
                                <option value="moderator">Moderator</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <button type="submit" name="create_user" class="btn btn-primary">Create User</button>
                    </form>
                </div>
            </div>
        </div>
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

        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
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

    <script>
        document.getElementById('searchUsers').addEventListener('input', function (event) {
            const query = event.target.value;

            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            fetch(`admin_dashboard.php?query=${encodeURIComponent(query)}`)
                .then((response) => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json(); // Преобразуем в JSON
                })
                .then((users) => {
                    if (!Array.isArray(users)) {
                        throw new Error('Unexpected response format');
                    }

                    // Очищаем таблицу
                    const userTableBody = document.querySelector('#user-list tbody');
                    if (!userTableBody) {
                        throw new TypeError('userTableBody is null');
                    }
                    userTableBody.innerHTML = '';

                    // Обновляем таблицу с пользователями
                    users.forEach((user) => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                    <td>${user.id}</td>
                    <td>${user.name}</td>
                    <td>${user.email}</td>
                    <td>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="${csrfToken}">
                            <input type="hidden" name="user_id" value="${user.id}">
                            <select name="role" class="form-select d-inline w-auto">
                                <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Admin</option>
                                <option value="moderator" ${user.role === 'moderator' ? 'selected' : ''}>Moderator</option>
                                <option value="farmer" ${user.role === 'farmer' ? 'selected' : ''}>Farmer</option>
                                <option value="customer" ${user.role === 'customer' ? 'selected' : ''}>Customer</option>
                            </select>
                            <button type="submit" name="update_role" class="btn btn-primary btn-sm">Update</button>
                        </form>
                    </td>
                    <td>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="${csrfToken}">
                            <input type="hidden" name="user_id" value="${user.id}">
                            <button type="submit" name="delete_user" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                `;
                        userTableBody.appendChild(row);
                    });
                })
                .catch((error) => {
                    console.error('Error fetching user data:', error);
                });
        });
    </script>


<?php
$content = ob_get_clean();
require '../interface/templates/layout.php';
