<?php
session_start();
require '../backend/db.php';

$title = 'Browse Products';

$query = "SELECT id, name FROM categories";
$categories = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;
$where_clause = $category_id ? "WHERE products.category_id = :category_id" : "";

$order_by = isset($_GET['order_by']) ? $_GET['order_by'] : null;
$order_clause = "";
if ($order_by === 'price_asc') {
    $order_clause = "ORDER BY products.price ASC";
} elseif ($order_by === 'price_desc') {
    $order_clause = "ORDER BY products.price DESC";
} elseif ($order_by === 'quantity') {
    $order_clause = "ORDER BY products.quantity DESC";
} elseif ($order_by === 'popularity') {
    $order_clause = "
        ORDER BY (
            SELECT COUNT(*) FROM orders WHERE orders.product_id = products.id
        ) DESC";
}

$query = "SELECT products.id, products.name, categories.name AS category, products.price, products.price_unit, products.quantity, products.quantity_unit, products.farmer_id
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

$query = "
    SELECT e.id AS event_id, e.name AS event_name, ei.image_path
    FROM events e
    JOIN eventimages ei ON e.id = ei.event_id
    WHERE e.date IS NOT NULL
    GROUP BY e.id
    ORDER BY ABS(DATEDIFF(NOW(), e.date)) ASC
    LIMIT 5
";

$sliderStmt = $pdo->query($query);
$sliderItems = $sliderStmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>
<?php if (!empty($sliderItems)): ?>
    <div id="heroSlider" class="carousel slide mb-5" data-bs-ride="carousel">
        <div class="carousel-inner">
        <?php foreach ($sliderItems as $index => $item): ?>
            <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                <a href="event.php?id=<?= $item['event_id'] ?>">
                    <img src="<?= htmlspecialchars($item['image_path']) ?>" 
                        class="d-block w-100 img-fluid" 
                        alt="<?= htmlspecialchars($item['event_name']) ?>">
                    <div class="carousel-caption d-none d-md-block">
                        <div class="caption-bg">
                            <h5><?= htmlspecialchars($item['event_name']) ?></h5>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>

        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#heroSlider" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#heroSlider" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </button>
    </div>
<?php endif; ?>

<h1 class="text-center mb-4">Browse products</h1>

    <div class="text-center mb-4">
        <a href="advanced_search.php" class="btn btn-secondary">Advanced Search</a>
    </div>

<div class="row g-3 align-items-center mb-4">
    <form method="GET" action="index.php" class="row">
        <div class="col-md-5">
            <label for="category_id" class="form-label">Filter by Category:</label>
            <select name="category_id" id="category_id" class="form-select">
                <option value="">All categories</option>
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

<div class="products-container">
    <?php foreach ($products as $product): ?>
        <div class="product-card">
            <a href="product.php?id=<?= $product['id'] ?>" class="product-link">
                <h3><?= htmlspecialchars($product['name']) ?></h3>
                <p>Category: <?= htmlspecialchars($product['category']) ?></p>
                <p>
                    $<?= number_format($product['price'], 2) ?>
                    <?= htmlspecialchars(str_replace('_', ' ', $product['price_unit'])) ?>
                </p>
                <?php if ($product['quantity'] > 0): ?>
                    <p>
                        Available: <?= htmlspecialchars($product['quantity']) ?>
                        <?= htmlspecialchars($product['quantity_unit']) ?>
                    </p>
                <?php else: ?>
                    <p class="text-danger">Out of stock</p>
                <?php endif; ?>

            </a>

            <?php if (isset($_SESSION['user_id']) && $product['quantity'] > 0): ?>
                <?php if ($_SESSION['user_role'] !== 'farmer' || $product['farmer_id'] !== $_SESSION['user_id']): ?>
                    <button type="button" class="btn btn-success btn-add-to-cart" data-bs-toggle="modal"
                            data-bs-target="#addToCartModal"
                            data-product-id="<?= $product['id'] ?>"
                            data-product-name="<?= htmlspecialchars($product['name']) ?>"
                            data-product-max="<?= $product['quantity'] ?>"
                            data-product-price-unit="<?= htmlspecialchars($product['price_unit']) ?>"
                            data-product-quantity-unit="<?= htmlspecialchars($product['quantity_unit']) ?>">Add to Cart
                    </button>
                <?php else: ?>
                    <button class="btn btn-secondary btn-add-to-cart" disabled>Cannot Add Own Product</button>
                <?php endif; ?>
            <?php else: ?>
                <button class="btn btn-secondary btn-add-to-cart" disabled>
                    <?= isset($_SESSION['user_id']) ? 'Out of Stock' : 'Log in to Add to Cart' ?>
                </button>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

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
                        <label for="modal-quantity" class="form-label" id="modal-quantity-label">Quantity</label>
                        <input type="number" id="modal-quantity" name="quantity" class="form-control" min="1" value="1">
                    </div>
                    <button type="submit" class="btn btn-success">Add to Cart</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const addToCartButtons = document.querySelectorAll('.btn-add-to-cart');

    addToCartButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const productId = this.getAttribute('data-product-id');
            const productName = this.getAttribute('data-product-name');
            const productMax = this.getAttribute('data-product-max');
            const priceUnit = this.getAttribute('data-product-price-unit');
            const productQuantityUnit = this.getAttribute('data-product-quantity-unit');

            document.getElementById('modal-product-id').value = productId;
            document.getElementById('modal-product-name').value = productName;

            const quantityInput = document.getElementById('modal-quantity');
            quantityInput.value = 1;
            quantityInput.setAttribute('max', productMax);

            if (priceUnit === 'per_unit') {
                quantityInput.setAttribute('step', '1');
                quantityInput.setAttribute('type', 'number');
            } else {
                quantityInput.setAttribute('step', '0.01');
                quantityInput.setAttribute('type', 'number');
            }
            document.getElementById('modal-quantity-label').innerText = 'Quantity (' + productQuantityUnit + ')';
        });
    });
});
</script>

<?php
$content = ob_get_clean();
require '../interface/templates/layout.php';
?>