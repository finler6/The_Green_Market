<?php
session_start();
require '../backend/db.php';

if (!in_array($_SESSION['user_role'], ['customer', 'farmer', 'admin', 'moderator'])) {
    header('Location: login.php');
    exit;
}

$title = 'My Orders';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$query = "
    SELECT orders.id AS order_id, orders.status, orders.order_date,
           products.id AS product_id, products.name AS product_name,
           orderitems.quantity, orderitems.quantity_unit, orderitems.price_per_unit, orderitems.price_unit,
           (orderitems.quantity * orderitems.price_per_unit) AS total_price
    FROM orders
    JOIN orderitems ON orders.id = orderitems.order_id
    JOIN products ON orderitems.product_id = products.id
    WHERE orders.customer_id = :customer_id
    ORDER BY orders.order_date DESC
";
$stmt = $pdo->prepare($query);
$stmt->execute(['customer_id' => $_SESSION['user_id']]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$farmer_orders = [];
if (in_array($_SESSION['user_role'], ['farmer', 'admin', 'moderator'])) {
    $farmer_query = "
        SELECT orders.id AS order_id, orders.status, orders.order_date,
               products.id AS product_id, products.name AS product_name,
               orderitems.quantity, orderitems.quantity_unit, orderitems.price_per_unit, orderitems.price_unit,
               (orderitems.quantity * orderitems.price_per_unit) AS total_price,
               users.name AS customer_name
        FROM orders
        JOIN orderitems ON orders.id = orderitems.order_id
        JOIN products ON orderitems.product_id = products.id
        JOIN users ON orders.customer_id = users.id
        WHERE products.farmer_id = :farmer_id AND orders.status = 'pending'
        ORDER BY orders.order_date DESC
    ";
    $farmer_stmt = $pdo->prepare($farmer_query);
    $farmer_stmt->execute(['farmer_id' => $_SESSION['user_id']]);
    $farmer_orders = $farmer_stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_action']) && in_array($_SESSION['user_role'], ['farmer', 'admin', 'moderator'])) {
    $order_id = (int)$_POST['order_id'];
    $action = $_POST['order_action']; // 'confirm' или 'cancel'
    $csrf_token = $_POST['csrf_token'] ?? '';

    if ($csrf_token !== ($_SESSION['csrf_token'] ?? '')) {
        $error = "Invalid CSRF token.";
    } elseif (in_array($action, ['confirm', 'cancel'])) {
        $new_status = $action === 'confirm' ? 'completed' : 'cancelled';
        $update_query = "UPDATE orders SET status = :new_status WHERE id = :order_id";
        $update_stmt = $pdo->prepare($update_query);
        $update_stmt->execute(['new_status' => $new_status, 'order_id' => $order_id]);
        $success = $action === 'confirm' ? 'Order confirmed successfully!' : 'Order cancelled successfully!';
    } else {
        $error = 'Invalid action.';
    }

    header('Location: my_orders.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $product_id = (int)$_POST['product_id'];
    $rating = (int)$_POST['rating'];
    $comment = trim($_POST['comment']);

    if ($csrf_token !== ($_SESSION['csrf_token'] ?? '')) {
        $error = "Invalid CSRF token.";
    } elseif ($product_id > 0 && $rating >= 1 && $rating <= 5 && !empty($comment)) {
        $check_query = "SELECT COUNT(*) FROM reviews WHERE product_id = :product_id AND user_id = :user_id";
        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->execute([
            'product_id' => $product_id,
            'user_id' => $_SESSION['user_id'],
        ]);
        $review_exists = $check_stmt->fetchColumn() > 0;

        if ($review_exists) {
            $error = "You have already left a review for this product.";
        } else {
            $query = "INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (:product_id, :user_id, :rating, :comment)";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                'product_id' => $product_id,
                'user_id' => $_SESSION['user_id'],
                'rating' => $rating,
                'comment' => $comment,
            ]);
            $success = "Review added successfully!";
        }
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

<?php if (!empty($farmer_orders)): ?>
    <h2 class="text-center mb-4">Orders to Confirm</h2>
    <div class="orders-container">
        <?php foreach ($farmer_orders as $order): ?>
            <div class="order-card">
                <h3>Order #<?= htmlspecialchars($order['order_id']) ?></h3>
                <p><strong>Customer:</strong> <?= htmlspecialchars($order['customer_name']) ?></p>
                <p><strong>Product:</strong> <?= htmlspecialchars($order['product_name']) ?></p>
                <p><strong>Quantity:</strong> <?= htmlspecialchars($order['quantity']) ?> <?= htmlspecialchars($order['quantity_unit']) ?></p>
                <p><strong>Total Price:</strong> $<?= number_format($order['total_price'], 2) ?></p>
                <p><strong>Order Date:</strong> <?= htmlspecialchars(date('F j, Y, g:i a', strtotime($order['order_date']))) ?></p>
                <form method="POST" action="../frontend/my_orders.php">
                    <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <button type="submit" name="order_action" value="confirm" class="btn btn-success btn-sm">Confirm</button>
                    <button type="submit" name="order_action" value="cancel" class="btn btn-danger btn-sm">Cancel</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
    <hr>
<?php endif; ?>

<?php if (empty($orders)): ?>
    <p class="text-muted text-center">You have no orders yet.</p>
<?php else: ?>
    <div class="orders-container">
        <?php
        $current_order_id = null;
        $reviewed_products_query = "
            SELECT product_id 
            FROM reviews 
            WHERE user_id = :user_id
        ";
        $reviewed_stmt = $pdo->prepare($reviewed_products_query);
        $reviewed_stmt->execute(['user_id' => $_SESSION['user_id']]);
        $reviewed_products = $reviewed_stmt->fetchAll(PDO::FETCH_COLUMN);
        ?>
        <?php foreach ($orders as $order): ?>
            <?php if ($current_order_id !== $order['order_id']): ?>
                <?php if ($current_order_id !== null): ?>
                    </div>
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

            <p>- <?= htmlspecialchars($order['product_name']) ?>: <?= htmlspecialchars($order['quantity']) ?> <?= htmlspecialchars($order['quantity_unit']) ?> x $<?= number_format($order['price_per_unit'], 2) ?> <?= htmlspecialchars(str_replace('_', ' ', $order['price_unit'])) ?> = $<?= number_format($order['total_price'], 2) ?></p>

            <?php if ($order['status'] === 'completed' && !in_array($order['product_id'], $reviewed_products)): ?>
                <button class="btn btn-primary btn-sm leave-review-btn"
                        data-bs-toggle="modal"
                        data-bs-target="#reviewModal"
                        data-product-id="<?= $order['product_id'] ?>"
                        data-product-name="<?= htmlspecialchars($order['product_name']) ?>">
                    Leave a Review
                </button>
            <?php endif; ?>

            <?php $current_order_id = $order['order_id']; ?>
        <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>


<div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reviewModalLabel">Leave a Review</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="../frontend/my_orders.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
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
<script>
    function initReviewModal() {
        const reviewModal = document.getElementById('reviewModal');
        const modalProductId = document.getElementById('modalProductId');
        const modalRating = document.getElementById('rating');
        const modalComment = document.getElementById('comment');
        const ratingStars = document.getElementById('ratingStars').querySelectorAll('.fa-star');

        document.querySelectorAll('.leave-review-btn').forEach(button => {
            button.addEventListener('click', function () {
                const productId = this.getAttribute('data-product-id');
                const productName = this.getAttribute('data-product-name');
                document.getElementById('reviewModalLabel').textContent = `Leave a Review for ${productName}`;
                modalProductId.value = productId;
                modalRating.value = '';
                modalComment.value = '';
                ratingStars.forEach(star => star.classList.remove('fa-solid', 'text-warning'));
                ratingStars.forEach(star => star.classList.add('fa-regular'));
            });
        });

        ratingStars.forEach(star => {
            star.addEventListener('click', function () {
                const ratingValue = this.getAttribute('data-value');
                modalRating.value = ratingValue;
                ratingStars.forEach(star => {
                    if (star.getAttribute('data-value') <= ratingValue) {
                        star.classList.add('fa-solid', 'text-warning');
                        star.classList.remove('fa-regular');
                    } else {
                        star.classList.add('fa-regular');
                        star.classList.remove('fa-solid', 'text-warning');
                    }
                });
            });
        });
    }

    document.addEventListener('DOMContentLoaded', initReviewModal);
</script>

<?php
$content = ob_get_clean();
require '../interface/templates/layout.php';
?>
