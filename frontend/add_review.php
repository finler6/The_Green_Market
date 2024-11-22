<?php
session_start();
require '../backend/db.php';
require '../interface/templates/navigation.php';
require '../backend/auth.php';
require '../backend/validation.php';

ensureRole('customer');

//genartion CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Получение списка купленных товаров
$query = "SELECT DISTINCT Products.id, Products.name
          FROM Orders
          JOIN Products ON Orders.product_id = Products.id
          WHERE Orders.customer_id = :customer_id";
$stmt = $pdo->prepare($query);
$stmt->execute(['customer_id' => $_SESSION['user_id']]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Обработка отправки отзыва
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token.');
    }
    $product_id = validateInt($_POST['product_id'], 1);
    $rating = validateInt($_POST['rating'], 1, 5);
    $comment = validateString($_POST['comment'], 500);

    if ($rating < 1 || $rating > 5) {
        $error = "Rating must be between 1 and 5.";
    } elseif (empty($product_id)) {
        $error = "Please select a product.";
    } else {
        // Проверяем, оставлял ли пользователь уже отзыв
        $query = "SELECT COUNT(*) FROM Reviews WHERE product_id = :product_id AND user_id = :user_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['product_id' => $product_id, 'user_id' => $_SESSION['user_id']]);
        $already_reviewed = $stmt->fetchColumn();

        if ($already_reviewed > 0) {
            $error = "You have already reviewed this product.";
        } else {
            // Добавляем отзыв
            $query = "INSERT INTO Reviews (product_id, user_id, rating, comment)
                      VALUES (:product_id, :user_id, :rating, :comment)";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                'product_id' => $product_id,
                'user_id' => $_SESSION['user_id'],
                'rating' => $rating,
                'comment' => $comment
            ]);
            $success = "Your review has been submitted!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Review</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-5">
<?php renderNavigation($_SESSION['user_role']); ?>
<h1>Add Review</h1>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<form method="POST" action="add_review.php">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <div class="mb-3">
        <label for="product_id" class="form-label">Product</label>
        <select id="product_id" name="product_id" class="form-select" required>
            <option value="">Select a product</option>
            <?php foreach ($products as $product): ?>
                <option value="<?= $product['id'] ?>"><?= htmlspecialchars($product['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="mb-3">
        <label for="rating" class="form-label">Rating (1-5)</label>
        <input type="number" id="rating" name="rating" class="form-control" min="1" max="5" required>
    </div>
    <div class="mb-3">
        <label for="comment" class="form-label">Comment (optional)</label>
        <textarea id="comment" name="comment" class="form-control" rows="3"></textarea>
    </div>
    <button type="submit" name="submit_review" class="btn btn-primary">Submit Review</button>
</form>
</body>
</html>
