<?php
session_start();
require '../backend/db.php';

if ($_SESSION['user_role'] !== 'customer') {
    header('Location: login.php');
    exit;
}

$title = 'My Orders';

// Получение заказов клиента
$query = "SELECT Orders.id AS order_id, Products.id AS product_id, Products.name AS product_name,
                 Orders.quantity, Orders.total_price, Orders.status, Orders.order_date
          FROM Orders
          JOIN Products ON Orders.product_id = Products.id
          WHERE Orders.customer_id = :customer_id
          ORDER BY Orders.order_date DESC";
$stmt = $pdo->prepare($query);
$stmt->execute(['customer_id' => $_SESSION['user_id']]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

<?php if (empty($orders)): ?>
    <p class="text-muted text-center">You have no orders yet.</p>
<?php else: ?>
    <div class="orders-container">
        <?php foreach ($orders as $order): ?>
            <div class="order-card">
                <h3>Order #<?= htmlspecialchars($order['order_id']) ?></h3>
                <p><strong>Product:</strong> <?= htmlspecialchars($order['product_name']) ?></p>
                <p><strong>Quantity:</strong> <?= htmlspecialchars($order['quantity']) ?></p>
                <p><strong>Total Price:</strong> $<?= htmlspecialchars($order['total_price']) ?></p>
                <p><strong>Status:</strong>
                    <span class="<?= $order['status'] === 'completed' ? 'text-success' : ($order['status'] === 'pending' ? 'text-warning' : 'text-danger') ?>">
                        <?= htmlspecialchars(ucfirst($order['status'])) ?>
                    </span>
                </p>
                <p><strong>Order Date:</strong> <?= htmlspecialchars(date('F j, Y, g:i a', strtotime($order['order_date']))) ?></p>
                <?php if ($order['status'] === 'completed'): ?>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#reviewModal" data-product-id="<?= $order['product_id'] ?>" data-product-name="<?= htmlspecialchars($order['product_name']) ?>">Leave a Review</button>
                <?php else: ?>
                    <span class="text-muted">No actions available</span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
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
                    <form method="POST" action="">
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
