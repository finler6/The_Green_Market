<?php
require_once '../backend/config.php';
function renderNavigation($role = null) {
    ?>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= BASE_URL ?>/frontend/index.php">Farm Market</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/frontend/index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/frontend/events.php">Events</a>
                    </li>
                    <?php if ($role === 'customer'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/frontend/customer_dashboard.php">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/frontend/cart.php">Cart</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/frontend/my_orders.php">My Orders</a>
                        </li>
                    <?php elseif ($role === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/frontend/admin_dashboard.php">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/frontend/manage_users.php">Manage Users</a>
                        </li>
                    <?php elseif ($role === 'moderator'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/frontend/moderator_dashboard.php">Dashboard</a>
                        </li>
                    <?php elseif ($role === 'farmer'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>t/frontend/farmer_dashboard.php">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/frontend/manage_orders.php">Manage Orders</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/frontend/old/manage_events.php">Manage Events</a>
                        </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= BASE_URL ?>/frontend/interests.php">My Interests</a>
                        </li>
                        <li class="nav-item">
                            <span class="navbar-text text-light me-2">
                                Hello, <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?>!
                            </span>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link btn btn-outline-light btn-sm" href="<?= BASE_URL ?>/frontend/logout.php">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link btn btn-outline-primary btn-sm" href="<?= BASE_URL ?>/frontend/old/login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link btn btn-outline-success btn-sm" href="<?= BASE_URL ?>/frontend/old/register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <?php
}
?>
