<?php
session_start();
require '../backend/db.php';

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$product_id) {
    header('Location: index.php');
    exit;
}

// Запрос информации о продукте
$query = "SELECT Products.id, Products.name, Categories.name AS category, Products.price, Products.quantity, Products.description 
          FROM Products
          JOIN Categories ON Products.category_id = Categories.id
          WHERE Products.id = :product_id";
$stmt = $pdo->prepare($query);
$stmt->execute(['product_id' => $product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo "Product not found!";
    exit;
}

// Запрос изображений продукта
$images_query = "SELECT image_path FROM ProductImages WHERE product_id = :product_id";
$images_stmt = $pdo->prepare($images_query);
$images_stmt->execute(['product_id' => $product_id]);
$product_images = $images_stmt->fetchAll(PDO::FETCH_ASSOC);

// Запрос отзывов и средней оценки
$reviews_query = "SELECT AVG(rating) AS average_rating, COUNT(*) AS review_count 
                  FROM Reviews 
                  WHERE product_id = :product_id";
$reviews_stmt = $pdo->prepare($reviews_query);
$reviews_stmt->execute(['product_id' => $product_id]);
$reviews_summary = $reviews_stmt->fetch(PDO::FETCH_ASSOC);

$average_rating = $reviews_summary['average_rating'] ? round($reviews_summary['average_rating'], 1) : 0;
$review_count = $reviews_summary['review_count'];

// Получение отзывов
$all_reviews_query = "SELECT Reviews.rating, Reviews.comment
                      FROM Reviews
                      WHERE product_id = :product_id
                      ORDER BY Reviews.id DESC";

$all_reviews_stmt = $pdo->prepare($all_reviews_query);
$all_reviews_stmt->execute(['product_id' => $product_id]);
$reviews = $all_reviews_stmt->fetchAll(PDO::FETCH_ASSOC);

// Генерация контента страницы
$title = htmlspecialchars($product['name']);
ob_start();
?>

<div class="product-details">
    <div class="product-info">
        <h1 class="product-title"><?= htmlspecialchars($product['name']) ?></h1>
        <p><strong>Category:</strong> <?= htmlspecialchars($product['category']) ?></p>
        <p><strong>Price:</strong> $<?= number_format($product['price'], 2) ?>/kg</p>
        <p><strong>Available:</strong> <?= htmlspecialchars($product['quantity']) ?> units</p>
        <p><strong>Description:</strong> <?= nl2br(htmlspecialchars($product['description'])) ?></p>

        <?php 
        $allowed_roles = ['customer', 'manager', 'admin']; // Разрешённые роли

        if ($product['quantity'] > 0 && isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], $allowed_roles)): ?>
            <form method="POST" action="add_to_cart.php">
                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                <button type="submit" class="btn btn-success">Add to Cart</button>
            </form>
        <?php elseif ($product['quantity'] <= 0): ?>
            <p class="text-danger">Out of stock</p>
        <?php else: ?>
            <p>Login with a valid role to purchase products.</p>
        <?php endif; ?>
    </div>

    <div class="product-gallery">
        <div class="gallery-container">
            <button class="gallery-prev">&#10094;</button>
            <div class="gallery-images">
                <?php if (!empty($product_images)): ?>
                    <?php foreach ($product_images as $image): ?>
                        <img src="../<?= htmlspecialchars($image['image_path']) ?>" alt="Product Image">
                    <?php endforeach; ?>
                <?php else: ?>
                    <img src="../images/placeholder.png" alt="No Image Available" class="placeholder-image">
                <?php endif; ?>
            </div>
            <button class="gallery-next">&#10095;</button>
        </div>
    </div>
</div>

<!-- Отображение рейтинга и числа отзывов -->
<div class="product-rating">
    <p>
        <strong>Average Rating:</strong> <?= $average_rating ?> / 5 
        <span class="stars">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <?php if ($i <= floor($average_rating)): ?>
                    <i class="fa fa-star"></i> <!-- Полная звезда -->
                <?php elseif ($i - $average_rating <= 0.5): ?>
                    <i class="fa fa-star-half-alt"></i> <!-- Половина звезды -->
                <?php else: ?>
                    <i class="fa fa-star-o"></i> <!-- Пустая звезда -->
                <?php endif; ?>
            <?php endfor; ?>
        </span>
        (<?= $review_count ?> reviews)
    </p>
</div>

<!-- Список отзывов -->
<div class="product-reviews">
    <h2>Reviews</h2>
    <?php if ($reviews): ?>
        <?php foreach ($reviews as $review): ?>
            <div class="review">
                <p>Rating: <?= str_repeat('⭐', (int)$review['rating']) ?></p>
                <p><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
            </div>
            <hr>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No reviews yet. Be the first to leave a review!</p>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require '../interface/templates/layout.php';
?>
