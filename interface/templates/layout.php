<?php
require_once '../backend/db.php';
$role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$formErrors = $_SESSION['form_errors'] ?? [];
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_errors'], $_SESSION['form_data']);

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    $current_date = date('Y-m-d');

    $query = "
        SELECT e.id AS event_id, e.name AS event_name, e.date AS event_date
        FROM userinterests ui
        JOIN events e ON ui.event_id = e.id
        LEFT JOIN notifications n ON n.event_id = e.id AND n.user_id = ui.user_id
        WHERE ui.user_id = :user_id
        AND e.date >= :current_date AND e.date <= DATE_ADD(:current_date, INTERVAL 1 DAY)
        AND (n.is_read = 0 OR n.is_read IS NULL)
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'user_id' => $user_id,
        'current_date' => $current_date
    ]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($events)) {
        $insert_stmt = $pdo->prepare("INSERT INTO notifications (user_id, event_id, is_read) VALUES (:user_id, :event_id, 0) ON DUPLICATE KEY UPDATE is_read = 0");
        foreach ($events as $event) {
            $insert_stmt->execute([
                'user_id' => $user_id,
                'event_id' => $event['event_id']
            ]);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" href="../icons/apple.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Green Market') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../interface/css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body>
<header class="main-header">
    <nav class="navbar">
        <div class="container navbar-container">
            <a href="index.php" class="navbar-logo">Green Market</a>
            <ul class="navbar-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="events.php">Events</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="../frontend/my_orders.php">My Orders</a></li>
                    <?php if (($_SESSION['user_role'] === 'admin') || ($_SESSION['user_role'] === 'moderator')): ?>
                        <li><a href="../frontend/manage_categories.php" class="btn-admin">Manage Categories</a></li>
                    <?php endif; ?>
                    <?php if ($_SESSION['user_role'] === 'admin'): ?>
                        <li><a href="../frontend/<?= htmlspecialchars($role) ?>_dashboard.php">Admin Panel</a></li>
                    <?php elseif ($_SESSION['user_role'] === 'farmer'): ?>
                        <li><a href="../frontend/<?= htmlspecialchars($role) ?>_dashboard.php">Dashboard</a></li>
                    <?php endif; ?>
                    <li class="navbar-notifications">
                        <a href="#" id="notificationIcon">
                            <img src="../icons/notificationIcon.png" alt="Notifications" class="notification-icon">
                            <span id="notificationCount" class="notification-count">0</span>
                        </a>
                        <div id="notificationPanel" class="notification-panel">
                            <div id="notificationList">
                                <p class="text-center">No new notifications.</p>
                            </div>
                            <button id="clearNotifications" class="btn btn-link">Mark all as read</button>
                        </div>
                    </li>
                    <li class="navbar-profile">
                        <a href="../frontend/profile.php">
                            <img src="../icons/profileIcon.png" alt="Profile" class="profile-icon">
                        </a>
                    </li>
                    <li class="navbar-cart">
                        <a href="../frontend/cart.php" class="cart-link">
                            <img src="../icons/cartIcon.png" alt="Cart" class="cart-icon">
                            <span class="cart-count"><?= isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0 ?></span>
                        </a>
                    </li>
                    <li class="navbar-logout">
                        <a href="../frontend/logout.php" class="btn-primary">Logout</a>
                    </li>
                <?php else: ?>
                    <li><a type="button" class="btn-primary" data-bs-toggle="modal" data-bs-target="#loginModal">Login</a></li>
                    <li><a type="button" class="btn-primary" data-bs-toggle="modal" data-bs-target="#registerModal">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>
</header>

<main class="main-content container">
    <div class="content-wrapper">
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?= $content ?? '<p>Welcome to Green Market!</p>' ?>
    </div>
</main>

<div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="loginModalLabel">Login</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
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
                        <a href="#" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#registerModal">Register</a>
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
                <form method="POST" action="../frontend/register_handler.php">
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

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const registerButton = document.querySelector('[data-bs-target="#registerModal"]');
        registerButton.addEventListener('click', () => {
            const loginModal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
            if (loginModal) {
                loginModal.hide();
            }

            const registerModal = new bootstrap.Modal(document.getElementById('registerModal'));
            registerModal.show();
        });

        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            modal.addEventListener('hidden.bs.modal', () => {
                document.body.classList.remove('modal-open');
                const backdrop = document.querySelector('.modal-backdrop');
                if (backdrop) backdrop.remove();
            });
        });
    });
</script>

<footer class="footer">
    <div class="container text-center">
        <p>&copy; <?= date('Y') ?> Green Market. All rights reserved.</p>
    </div>
</footer>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const notificationIcon = document.getElementById('notificationIcon');
        const notificationPanel = document.getElementById('notificationPanel');
        const notificationCount = document.getElementById('notificationCount');
        const notificationList = document.getElementById('notificationList');
        const clearNotifications = document.getElementById('clearNotifications');

        function loadNotifications() {
            fetch('notification.php?action=fetch')
                    .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const notifications = data.notifications;
                        notificationList.innerHTML = '';
                        if (notifications.length > 0) {
                            notifications.forEach(notification => {
                                const eventLink = document.createElement('a');
                                eventLink.href = 'event.php?id=' + notification.event_id;
                                eventLink.textContent = `${notification.event_name} is happening on ${notification.event_date}`;
                                eventLink.classList.add('notification-item');
                                notificationList.appendChild(eventLink);
                            });
                            notificationCount.textContent = notifications.length;
                        } else {
                            notificationList.innerHTML = '<p class="text-center">No new notifications.</p>';
                            notificationCount.textContent = '0';
                        }
                    } else {
                        console.error('Error fetching notifications:', data.error);
                        notificationList.innerHTML = '<p class="text-center text-danger">Error loading notifications.</p>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }

        loadNotifications();
    
        setInterval(loadNotifications, 60000);
        notificationIcon.addEventListener('click', (e) => {
            e.preventDefault();
            notificationPanel.classList.toggle('show');
        });

        document.addEventListener('click', (e) => {
            if (!notificationIcon.contains(e.target) && !notificationPanel.contains(e.target)) {
                notificationPanel.classList.remove('show');
            }
        });

        clearNotifications.addEventListener('click', () => {
            fetch('notification.php?action=mark_as_read')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        notificationList.innerHTML = '<p class="text-center">No new notifications.</p>';
                        notificationCount.textContent = '0';
                    } else {
                        console.error('Error marking notifications as read:', data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        });
    });
    </script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../interface/js/script.js"></script>
</body>
</html>
