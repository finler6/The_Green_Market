<?php
$role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Farm Market') ?></title>
    <link rel="stylesheet" href="../interface/css/styles.css"> <!-- Подключение кастомных стилей -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"> <!-- Иконки -->
</head>
<body>
<!-- Навигация -->
<header class="main-header">
    <nav class="navbar">
        <div class="container navbar-container">
            <a href="index.php" class="navbar-logo">Farm Market</a>
            <ul class="navbar-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="events.php">Events</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="../../farm_market/frontend/<?= htmlspecialchars($role) ?>_dashboard.php">Dashboard</a></li>
                    <li><a href="../../farm_market/frontend/cart.php">Cart</a></li>
                    <li><a href="../../farm_market/frontend/my_orders.php">My Orders</a></li>
                    <li><a href="../../farm_market/frontend/logout.php" class="btn-logout">Logout</a></li>
                <?php else: ?>
                    <li><a href="../../farm_market/frontend/login.php" class="btn-primary">Login</a></li>
                    <li><a href="../../farm_market/frontend/register.php" class="btn-primary">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>
</header>

<!-- Основной контент -->
<main class="main-content container">
    <div class="content-wrapper">
        <!-- Уведомления -->
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Основной блок -->
        <?= $content ?? '<p>Welcome to Farm Market!</p>' ?>
    </div>
</main>

<!-- Подвал -->
<footer class="footer">
    <div class="container text-center">
        <p>&copy; <?= date('Y') ?> Farm Market. All rights reserved.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> <!-- Bootstrap -->
<script src="../interface/js/script.js"></script> <!-- Ваши кастомные скрипты -->
</body>
</html>
