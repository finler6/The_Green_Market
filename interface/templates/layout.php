<?php
require_once '../backend/config.php';
$role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : '';

// Генерация CSRF токена
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Получение уведомлений
$formErrors = $_SESSION['form_errors'] ?? [];
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_data']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Green Market') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/interface/css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body>
<!-- Навигация -->
<header class="main-header">
    <nav class="navbar">
        <div class="container navbar-container">
            <a href="index.php" class="navbar-logo">Green Market</a>
            <ul class="navbar-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="events.php">Events</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="<?= BASE_URL ?>/frontend/<?= htmlspecialchars($role) ?>_dashboard.php">Dashboard</a></li>
                    <li>
                        <a href="<?= BASE_URL ?>/frontend/cart.php">
                            Cart (<?= isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0 ?>)
                        </a>
                    </li>
                    <li><a href="<?= BASE_URL ?>/frontend/my_orders.php">My Orders</a></li>
                    <li><a href="<?= BASE_URL ?>/frontend/logout.php" class="btn-logout">Logout</a></li>
                <?php else: ?>
                    <li><a type="button" class="btn-primary" data-bs-toggle="modal" data-bs-target="#loginModal">Login</a></li>
                    <li><a type="button" class="btn-primary" data-bs-toggle="modal" data-bs-target="#registerModal">Register</a></li>
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
        <?= $content ?? '<p>Welcome to Green Market!</p>' ?>
    </div>
</main>

<!-- Модальное окно для входа -->
<div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="loginModalLabel">Login</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Форма входа -->
                <form method="POST" action="login_handler.php">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="d-flex justify-content-between">
                        <button type="submit" class="btn btn-success">Login</button>
                        <a href="register.php" class="btn btn-secondary">Register</a>
                    </div>
                    <div class="mt-3 text-center">
                        <a href="forgot_password.php" class="text-decoration-none">Forgot Password?</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade <?= !empty($formErrors) ? 'show' : '' ?>" id="registerModal" tabindex="-1" aria-labelledby="registerModalLabel" aria-hidden="<?= empty($formErrors) ? 'true' : 'false' ?>" style="<?= !empty($formErrors) ? 'display: block;' : '' ?>">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="registerModalLabel">Register</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if (!empty($formErrors)): ?>
                    <div class="alert alert-danger">
                        <ul>
                            <?php foreach ($formErrors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <form method="POST" action="<?= BASE_URL ?>/frontend/register_handler.php">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($formData['name'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($formData['email'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    <div class="d-flex justify-content-between">
                        <button type="submit" class="btn btn-success">Register</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Подвал -->
<footer class="footer">
    <div class="container text-center">
        <p>&copy; <?= date('Y') ?> Green Market. All rights reserved.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> <!-- Bootstrap -->
<script src="<?= BASE_URL ?>/interface/js/script.js"></script> <!-- Ваши кастомные скрипты -->
</body>
</html>
