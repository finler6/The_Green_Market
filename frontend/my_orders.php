<?php
session_start();
require '../backend/db.php';

if (!in_array($_SESSION['user_role'], ['customer', 'farmer', 'admin', 'moderator'])) {
    header('Location: login.php');
    exit;
}

$title = 'My Orders';

// Получение заказов клиента
$query = "
    SELECT Orders.id AS order_id, Orders.status, Orders.order_date,
           Products.id AS product_id, Products.name AS product_name,
           OrderItems.quantity, OrderItems.price_per_unit,
           (OrderItems.quantity * OrderItems.price_per_unit) AS total_price
    FROM Orders
    JOIN OrderItems ON Orders.id = OrderItems.order_id
    JOIN Products ON OrderItems.product_id = Products.id
    WHERE Orders.customer_id = :customer_id
    ORDER BY Orders.order_date DESC
";
$stmt = $pdo->prepare($query);
$stmt->execute(['customer_id' => $_SESSION['user_id']]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получение заказов, отправленных фермеру, для подтверждения/отмены
$farmer_orders = [];
if (in_array($_SESSION['user_role'], ['farmer', 'admin', 'moderator'])) {
    $farmer_query = "
        SELECT Orders.id AS order_id, Orders.status, Orders.order_date,
               Products.id AS product_id, Products.name AS product_name,
               OrderItems.quantity, OrderItems.price_per_unit,
               (OrderItems.quantity * OrderItems.price_per_unit) AS total_price,
               Customers.name AS customer_name
        FROM Orders
        JOIN OrderItems ON Orders.id = OrderItems.order_id
        JOIN Products ON OrderItems.product_id = Products.id
        JOIN Users AS Customers ON Orders.customer_id = Customers.id
        WHERE Products.farmer_id = :farmer_id AND Orders.status = 'pending'
        ORDER BY Orders.order_date DESC
    ";
    $farmer_stmt = $pdo->prepare($farmer_query);
    $farmer_stmt->execute(['farmer_id' => $_SESSION['user_id']]);
    $farmer_orders = $farmer_stmt->fetchAll(PDO::FETCH_ASSOC);
}


// Обработка подтверждения или отмены заказа фермером
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_action']) && $_SESSION['user_role'] === 'farmer') {
    $order_id = (int)$_POST['order_id'];
    $action = $_POST['order_action']; // 'confirm' или 'cancel'

    if (in_array($action, ['confirm', 'cancel'])) {
        $new_status = $action === 'confirm' ? 'completed' : 'cancelled';
        $update_query = "UPDATE Orders SET status = :new_status WHERE id = :order_id";
        $update_stmt = $pdo->prepare($update_query);
        $update_stmt->execute(['new_status' => $new_status, 'order_id' => $order_id]);
        $success = $action === 'confirm' ? 'Order confirmed successfully!' : 'Order cancelled successfully!';
    } else {
        $error = 'Invalid action.';
    }

    // Перезагрузка страницы
    header('Location: my_orders.php');
    exit;
}


// Обработка добавления отзыва
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $product_id = (int)$_POST['product_id'];
    $rating = (int)$_POST['rating'];
    $comment = trim($_POST['comment']);

    if ($product_id > 0 && $rating >= 1 && $rating <= 5 && !empty($comment)) {
        $query = "INSERT INTO Reviews (product_id, user_id, rating, comment) VALUES (:product_id, :user_id, :rating, :comment)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            'product_id' => $product_id,
            'user_id' => $_SESSION['user_id'],
            'rating' => $rating,
            'comment' => $comment,
        ]);
        $success = "Review added successfully!";
    } else {
        $error = "Please provide a valid rating and comment.";
    }
}

ob_start();
?>
<h1 class="text-center mb-4">My Orders</h1>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($_SESSION['user_role'] === 'farmer' && !empty($farmer_orders)): ?>
    <h2 class="text-center mb-4">Orders to Confirm</h2>
    <div class="orders-container">
        <?php foreach ($farmer_orders as $order): ?>
            <div class="order-card">
                <h3>Order #<?= htmlspecialchars($order['order_id']) ?></h3>
                <p><strong>Customer:</strong> <?= htmlspecialchars($order['customer_name']) ?></p>
                <p><strong>Product:</strong> <?= htmlspecialchars($order['product_name']) ?></p>
                <p><strong>Quantity:</strong> <?= htmlspecialchars($order['quantity']) ?></p>
                <p><strong>Total Price:</strong> $<?= number_format($order['total_price'], 2) ?></p>
                <p><strong>Order Date:</strong> <?= htmlspecialchars(date('F j, Y, g:i a', strtotime($order['order_date']))) ?></p>
                <form method="POST" action="my_orders.php">
                    <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                    <button type="submit" name="order_action" value="confirm" class="btn btn-success btn-sm">Confirm</button>
                    <button type="submit" name="order_action" value="cancel" class="btn btn-danger btn-sm">Cancel</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
    <hr>
<?php endif; ?>

<!-- Обычные заказы пользователя -->
<?php if (empty($orders)): ?>
    <p class="text-muted text-center">You have no orders yet.</p>
<?php else: ?>
    <div class="orders-container">
        <?php $current_order_id = null; ?>
        <?php foreach ($orders as $order): ?>
            <?php if ($current_order_id !== $order['order_id']): ?>
                <?php if ($current_order_id !== null): ?>
                    </div> <!-- Закрываем предыдущий order-card -->
                <?php endif; ?>
                <div class="order-card">
                    <h3>Order #<?= htmlspecialchars($order['order_id']) ?></h3>
                    <p><strong>Status:</strong>
                        <span class="<?= $order['status'] === 'completed' ? 'text-success' : ($order['status'] === 'pending' ? 'text-warning' : 'text-danger') ?>">
                            <?= htmlspecialchars(ucfirst($order['status'])) ?>
                        </span>
                    </p>
                    <p><strong>Order Date:</strong> <?= htmlspecialchars(date('F j, Y, g:i a', strtotime($order['order_date']))) ?></p>
                    <h4>Items:</h4>
            <?php endif; ?>

            <p>- <?= htmlspecialchars($order['product_name']) ?>: <?= htmlspecialchars($order['quantity']) ?> x $<?= number_format($order['price_per_unit'], 2) ?> = $<?= number_format($order['total_price'], 2) ?></p>

            <?php if ($order['status'] === 'completed'): ?>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#reviewModal" data-product-id="<?= $order['product_id'] ?>" data-product-name="<?= htmlspecialchars($order['product_name']) ?>">Leave a Review</button>
            <?php endif; ?>

            <?php $current_order_id = $order['order_id']; ?>
        <?php endforeach; ?>
        </div> <!-- Закрываем последний order-card -->
    </div>
<?php endif; ?>

<!-- Модальное окно для оставления отзыва -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reviewModalLabel">Leave a Review</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="my_orders.php">
                    <input type="hidden" name="product_id" id="modalProductId" value="">
                    <div class="mb-3">
                        <label for="rating" class="form-label">Rating</label>
                        <div id="ratingStars" class="rating-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fa fa-star fa-regular" data-value="<?= $i ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="rating" id="rating" required>
                    </div>
                    <div class="mb-3">
                        <label for="comment" class="form-label">Comment</label>
                        <textarea name="comment" id="comment" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="submit_review" class="btn btn-success">Submit Review</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require '../interface/templates/layout.php';
?>
