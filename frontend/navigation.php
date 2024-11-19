<?php
function renderNavigation($role = null) {
    if (empty($_SESSION['user_id'])) {
        echo '
            <nav class="mb-4">
                <a href="index.php" class="btn btn-primary">Home</a>
                <a href="login.php" class="btn btn-secondary">Login</a>
            </nav>
        ';
    } else {
        switch ($role) {
            case 'customer':
                echo '
                    <nav class="mb-4">
                        <a href="index.php" class="btn btn-primary">Home</a>
                        <a href="customer_dashboard.php" class="btn btn-secondary">Dashboard</a>
                        <a href="cart.php" class="btn btn-warning">Cart</a>
                        <a href="my_orders.php" class="btn btn-info">My Orders</a>
                        <a href="logout.php" class="btn btn-danger">Logout</a>
                    </nav>
                ';
                break;

            case 'admin':
                echo '
                    <nav class="mb-4">
                        <a href="index.php" class="btn btn-primary">Home</a>
                        <a href="admin_dashboard.php" class="btn btn-secondary">Dashboard</a>
                        <a href="manage_users.php" class="btn btn-info">Manage Users</a>
                        <a href="logout.php" class="btn btn-danger">Logout</a>
                    </nav>
                ';
                break;

            case 'moderator':
                echo '
                    <nav class="mb-4">
                        <a href="index.php" class="btn btn-primary">Home</a>
                        <a href="moderator_dashboard.php" class="btn btn-secondary">Dashboard</a>
                        <a href="logout.php" class="btn btn-danger">Logout</a>
                    </nav>
                ';
                break;

            case 'farmer':
                echo '
                    <nav class="mb-4">
                        <a href="index.php" class="btn btn-primary">Home</a>
                        <a href="farmer_dashboard.php" class="btn btn-secondary">Dashboard</a>
                        <a href="manage_orders.php" class="btn btn-info">Manage Orders</a>
                        <a href="logout.php" class="btn btn-danger">Logout</a>
                    </nav>
                ';
                break;


            default:
                echo '
                    <nav class="mb-4">
                        <a href="index.php" class="btn btn-primary">Home</a>
                        <a href="logout.php" class="btn btn-danger">Logout</a>
                    </nav>
                ';
                break;
        }
    }
}
