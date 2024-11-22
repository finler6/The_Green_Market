<?php
session_start();
require '../backend/db.php';
require '../backend/auth.php';
require '../interface/templates/navigation.php';

ensureRole('customer');

// CSRF токен
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Обработка добавления в корзину
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token.');
    }

    $product_id = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];

    if ($product_id > 0 && $quantity > 0) {
        $query = "SELECT quantity FROM Products WHERE id = :product_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['product_id' => $product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($product && $product['quantity'] >= $quantity) {
            $_SESSION['cart'][$product_id] = $quantity;
            $success = "Product added to cart!";
        } else {
            $error = "Not enough stock available.";
        }
    }
}

// Обработка предложения категории
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['propose_category'])) {
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token.');
    }

    $name = trim($_POST['name']);
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

    if (!empty($name)) {
        $query = "INSERT INTO CategoryProposals (name, parent_id, user_id) VALUES (:name, :parent_id, :user_id)";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['name' => $name, 'parent_id' => $parent_id, 'user_id' => $_SESSION['user_id']]);
        $success = "Your proposal has been submitted for review.";
    } else {
        $error = "Category name cannot be empty.";
    }
}

// Получение списка товаров
$query = "SELECT Products.id, Products.name, Categories.name AS category, Products.price, Products.quantity, Products.description
          FROM Products
          JOIN Categories ON Products.category_id = Categories.id";
$products = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Получение списка категорий для модального окна
$query = "SELECT id, name FROM Categories";
$categories = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-5">
<?php renderNavigation($_SESSION['user_role']); ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<h1>Browse Products</h1>
<div class="mb-4">
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#proposeCategoryModal">
        Propose New Category
    </button>
</div>

<!-- Modal для предложения категории -->
<div class="modal fade" id="proposeCategoryModal" tabindex="-1" aria-labelledby="proposeCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="proposeCategoryModalLabel">Propose New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="customer_dashboard.php">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <div class="mb-3">
                        <label for="categoryName" class="form-label">Category Name</label>
                        <input type="text" name="name" id="categoryName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="parentCategory" class="form-label">Parent Category</label>
                        <select name="parent_id" id="parentCategory" class="form-select">
                            <option value="">None</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="propose_category" class="btn btn-primary">Submit Proposal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Список товаров -->
<table class="table table-striped mt-4">
    <thead>
    <tr>
        <th>Name</th>
        <th>Category</th>
        <th>Price</th>
        <th>Available</th>
        <th>Description</th>
        <th>Action</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($products as $product): ?>
        <tr>
            <td><?= htmlspecialchars($product['name']) ?></td>
            <td><?= htmlspecialchars($product['category']) ?></td>
            <td><?= htmlspecialchars($product['price']) ?></td>
            <td><?= htmlspecialchars($product['quantity']) ?></td>
            <td><?= htmlspecialchars($product['description']) ?></td>
            <td>
                <?php if ($product['quantity'] > 0): ?>
                    <form method="POST" action="customer_dashboard.php" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                        <input type="number" name="quantity" class="form-control d-inline w-25" min="1" max="<?= $product['quantity'] ?>" required>
                        <button type="submit" name="add_to_cart" class="btn btn-success btn-sm">Add to Cart</button>
                    </form>
                <?php else: ?>
                    <span class="text-danger">Out of stock</span>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
