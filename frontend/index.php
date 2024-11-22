<?php
session_start();
require '../backend/db.php';

$title = 'Browse Products';

// Получение категорий для фильтрации
$query = "SELECT id, name FROM Categories";
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
$query = "SELECT Products.id, Products.name, Categories.name AS category, Products.price, Products.quantity 
          FROM Products
          JOIN Categories ON Products.category_id = Categories.id
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
                <h3><?= htmlspecialchars($product['name']) ?></h3>
                <p>Category: <?= htmlspecialchars($product['category']) ?></p>
                <p>$<?= number_format($product['price'], 2) ?>/kg</p>
                <?php if ($product['quantity'] > 0): ?>
                    <p>Available: <?= htmlspecialchars($product['quantity']) ?> units</p>
                    <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'customer'): ?>
                        <form method="POST" action="add_to_cart.php">
                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                            <button type="submit" class="btn btn-success btn-add-to-cart">Add to Cart</button>
                        </form>
                    <?php else: ?>
                        <button class="btn btn-secondary btn-add-to-cart" disabled>Add to Cart</button>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-danger">Out of stock</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php
$content = ob_get_clean();
require '../interface/templates/layout.php';
