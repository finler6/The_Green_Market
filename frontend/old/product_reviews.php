<?php
session_start();
require '../backend/auth.php';
require '../backend/db.php';
require '../backend/validation.php';
require '../interface/templates/navigation.php';

// Проверяем, авторизован ли пользователь
ensureLoggedIn();

// Проверяем роль пользователя
$is_admin_or_moderator = ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'moderator');

// Получение ID товара
$product_id = isset($_GET['product_id']) ? validateInt($_GET['product_id'], 1) : null;
if (!$product_id) {
    header('Location: index.php');
    exit;
}

// Получение данных товара
$query = "SELECT name, description FROM Products WHERE id = :product_id";
$stmt = $pdo->prepare($query);
$stmt->execute(['product_id' => $product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: index.php');
    exit;
}

// Получение отзывов для товара
$query = "SELECT Reviews.id, Reviews.rating, Reviews.comment, Users.name AS user_name
          FROM Reviews
          JOIN Users ON Reviews.user_id = Users.id
          WHERE Reviews.product_id = :product_id
          ORDER BY Reviews.id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute(['product_id' => $product_id]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Обработка удаления отзыва
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_review']) && $is_admin_or_moderator) {
    $review_id = validateInt($_POST['review_id'], 1);
    if ($review_id) {
        $query = "DELETE FROM Reviews WHERE id = :review_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['review_id' => $review_id]);
        $success = "Review deleted successfully!";
        header("Location: product_reviews.php?product_id=$product_id");
        exit;
    } else {
        $error = "Invalid review ID.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Reviews</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-5">
<?php renderNavigation($_SESSION['user_role']); ?>

<h1>Reviews for <?= htmlspecialchars($product['name']) ?></h1>
<p><?= htmlspecialchars($product['description']) ?></p>

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (empty($reviews)): ?>
    <p class="text-muted">No reviews yet.</p>
<?php else: ?>
    <table class="table table-striped">
        <thead>
        <tr>
            <th>User</th>
            <th>Rating</th>
            <th>Comment</th>
            <?php if ($is_admin_or_moderator): ?>
                <th>Action</th>
            <?php endif; ?>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($reviews as $review): ?>
            <tr>
                <td><?= htmlspecialchars($review['user_name']) ?></td>
                <td><?= htmlspecialchars($review['rating']) ?></td>
                <td><?= htmlspecialchars($review['comment']) ?></td>
                <?php if ($is_admin_or_moderator): ?>
                    <td>
                        <form method="POST" action="product_reviews.php?product_id=<?= $product_id ?>" class="d-inline">
                            <input type="hidden" name="review_id" value="<?= htmlspecialchars($review['id']) ?>">
                            <button type="submit" name="delete_review" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</body>
</html>
