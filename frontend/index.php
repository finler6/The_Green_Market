<?php
session_start();
require '../backend/db.php';

$title = 'Browse Products';

// Получение категорий для фильтрации
$query = "SELECT id, name FROM categories";
$categories = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Фильтрация товаров
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;
$where_clause = $category_id ? "WHERE Products.category_id = :category_id" : "";

// Сортировка товаров
$order_by = isset($_GET['order_by']) ? $_GET['order_by'] : null;
$order_clause = "";
if ($order_by === 'price_asc') {
    $order_clause = "ORDER BY Products.price ASC";
} elseif ($order_by === 'price_desc') {
    $order_clause = "ORDER BY Products.price DESC";
} elseif ($order_by === 'quantity') {
    $order_clause = "ORDER BY Products.quantity DESC";
} elseif ($order_by === 'popularity') {
    $order_clause = "
        ORDER BY (
            SELECT COUNT(*) FROM Orders WHERE Orders.product_id = Products.id
        ) DESC";
}

// Формирование запроса
$query = "SELECT products.id, products.name, categories.name AS category, products.price, products.quantity 
          FROM products
          JOIN categories ON products.category_id = categories.id
          $where_clause
          $order_clause";
$stmt = $pdo->prepare($query);
if ($category_id) {
    $stmt->execute(['category_id' => $category_id]);
} else {
    $stmt->execute();
}
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Генерация контента
ob_start();
?>
<h1 class="text-center mb-4">Browse Products</h1>

<!-- Форма фильтрации и сортировки -->
<div class="row g-3 align-items-center mb-4">
    <form method="GET" action="index.php" class="row">
        <div class="col-md-5">
            <label for="category_id" class="form-label">Filter by Category:</label>
            <select name="category_id" id="category_id" class="form-select">
                <option value="">All Categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= $category['id'] ?>" <?= $category_id == $category['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-5">
            <label for="order_by" class="form-label">Sort by:</label>
            <select name="order_by" id="order_by" class="form-select">
                <option value="">Default</option>
                <option value="price_asc" <?= $order_by === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                <option value="price_desc" <?= $order_by === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                <option value="quantity" <?= $order_by === 'quantity' ? 'selected' : '' ?>>Quantity</option>
                <option value="popularity" <?= $order_by === 'popularity' ? 'selected' : '' ?>>Popularity</option>
            </select>
        </div>
        <div class="col-md-2 align-self-end">
            <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
        </div>
    </form>
</div>


    <!-- Карточки товаров -->
    <div class="products-container">
        <?php foreach ($products as $product): ?>
            <div class="product-card">
                <!-- Ссылка на страницу продукта -->
                <a href="product.php?id=<?= $product['id'] ?>" class="product-link">
                    <h3><?= htmlspecialchars($product['name']) ?></h3>
                    <p>Category: <?= htmlspecialchars($product['category']) ?></p>
                    <p>$<?= number_format($product['price'], 2) ?>/kg</p>
                    <?php if ($product['quantity'] > 0): ?>
                        <p>Available: <?= htmlspecialchars($product['quantity']) ?> units</p>
                    <?php else: ?>
                        <p class="text-danger">Out of stock</p>
                    <?php endif; ?>
                </a>

                <!-- Кнопка Add to Cart -->
                <?php if (($product['quantity'] > 0 && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'customer')
                || ($product['quantity'] > 0 && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'moderator')
                || ($product['quantity'] > 0 && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin')): ?>
                    <button type="button" class="btn btn-success btn-add-to-cart" data-bs-toggle="modal"
                            data-bs-target="#addToCartModal"
                            data-product-id="<?= $product['id'] ?>"
                            data-product-name="<?= htmlspecialchars($product['name']) ?>"
                            data-product-max="<?= $product['quantity'] ?>">Add to Cart
                    </button>
                <?php else: ?>
                    <button class="btn btn-secondary btn-add-to-cart" disabled>Add to Cart</button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Модальное окно -->
    <div class="modal fade" id="addToCartModal" tabindex="-1" aria-labelledby="addToCartModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addToCartModalLabel">Add to Cart</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="add_to_cart.php">
                        <input type="hidden" id="modal-product-id" name="product_id" value="">
                        <div class="mb-3">
                            <label for="modal-product-name" class="form-label">Product</label>
                            <input type="text" id="modal-product-name" class="form-control" value="" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="modal-quantity" class="form-label">Quantity</label>
                            <input type="number" id="modal-quantity" name="quantity" class="form-control" min="1" value="1">
                        </div>
                        <button type="submit" class="btn btn-success">Add to Cart</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php
$content = ob_get_clean();
require '../interface/templates/layout.php';
