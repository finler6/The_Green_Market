<?php
session_start();
require '../backend/db.php';

$role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : '';
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$product_id) {
    header('Location: index.php');
    exit;
}

// Запрос информации о продукте
$query = "SELECT Products.id, Products.name, Products.category_id, Categories.name AS category, Products.price, Products.quantity, Products.description 
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
$product_images = $images_stmt->fetchAll(PDO::FETCH_COLUMN);

// Если нет изображений, используем placeholder
if (empty($product_images)) {
    $product_images = ['../images/placeholder.png'];
}

// Запрос отзывов и средней оценки
$reviews_query = "SELECT AVG(rating) AS average_rating, COUNT(*) AS review_count 
                  FROM Reviews 
                  WHERE product_id = :product_id";
$reviews_stmt = $pdo->prepare($reviews_query);
$reviews_stmt->execute(['product_id' => $product_id]);
$reviews_summary = $reviews_stmt->fetch(PDO::FETCH_ASSOC);

$average_rating = $reviews_summary['average_rating'] ? round($reviews_summary['average_rating'], 1) : 0;
$review_count = $reviews_summary['review_count'];

// Получение всех отзывов
$all_reviews_query = "SELECT Reviews.rating, Reviews.comment
                      FROM Reviews
                      WHERE product_id = :product_id
                      ORDER BY Reviews.id DESC";

$all_reviews_stmt = $pdo->prepare($all_reviews_query);
$all_reviews_stmt->execute(['product_id' => $product_id]);
$reviews = $all_reviews_stmt->fetchAll(PDO::FETCH_ASSOC);

// Обновление продукта (включая удаление/добавление изображений)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_product'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $product_id = (int)$_POST['product_id'];

    if ($csrf_token !== ($_SESSION['csrf_token'] ?? '')) {
        die('Invalid CSRF token.');
    }

    // Проверяем, является ли текущий пользователь владельцем продукта
    $farmer_id = $_SESSION['user_id'] ?? null; // ID текущего фермера из сессии
    $query = "SELECT farmer_id FROM Products WHERE id = :product_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['product_id' => $product_id]);
    $owner_id = $stmt->fetchColumn();

    if (!$farmer_id || $owner_id !== $farmer_id) {
        die('You are not authorized to edit this product.');
    }

    // Данные из формы
    $name = htmlspecialchars(trim($_POST['name']));
    $price = (float)$_POST['price'];
    $quantity = (int)$_POST['quantity'];
    $description = htmlspecialchars(trim($_POST['description']));
    $new_images = $_POST['new_images'] ?? [];
    $delete_images = $_POST['delete_images'] ?? [];

    if (!empty($name) && $price > 0 && $quantity >= 0) {
        // Обновление основных данных продукта
        $query = "UPDATE Products 
                  SET name = :name, price = :price, quantity = :quantity, description = :description 
                  WHERE id = :product_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            'name' => $name,
            'price' => $price,
            'quantity' => $quantity,
            'description' => $description,
            'product_id' => $product_id,
        ]);

        // Удаление выбранных изображений
        if (!empty($delete_images)) {
            $delete_query = "DELETE FROM ProductImages WHERE product_id = :product_id AND image_path = :image_path";
            $delete_stmt = $pdo->prepare($delete_query);
            foreach ($delete_images as $image) {
                $image_path = htmlspecialchars(trim($image));
                if (file_exists($image_path)) {
                    unlink($image_path); // Удаляем файл из файловой системы
                }
                $delete_stmt->execute([
                    'product_id' => $product_id,
                    'image_path' => $image_path,
                ]);
            }
        }

        // Добавление новых изображений
        if (!empty($new_images)) {
            $insert_query = "INSERT INTO ProductImages (product_id, image_path) VALUES (:product_id, :image_path)";
            $insert_stmt = $pdo->prepare($insert_query);
            foreach ($new_images as $image) {
                $image_path = "../images/" . basename($image);
                if (file_exists($image_path) && is_file($image_path)) { // Проверяем существование файла
                    $insert_stmt->execute([
                        'product_id' => $product_id,
                        'image_path' => $image_path,
                    ]);
                } else {
                    // Логика обработки ошибок, если изображение не существует
                    $error = "The image '$image_path' does not exist or is not a valid file.";
                }
            }
        }

        if (!empty($_POST['attributes'])) {
            foreach ($_POST['attributes'] as $attributeId => $value) {
                $query = "INSERT INTO ProductAttributeValues (product_id, attribute_id, value) 
                  VALUES (:product_id, :attribute_id, :value)
                  ON DUPLICATE KEY UPDATE value = :value";
                $stmt = $pdo->prepare($query);
                $stmt->execute([
                    'product_id' => $product_id,
                    'attribute_id' => $attributeId,
                    'value' => htmlspecialchars(trim($value)),
                ]);
            }
        }

        // Перенаправление после успешного редактирования
        header("Location: product.php?id=$product_id");
        exit;
    } else {
        $error = "Please fill all required fields.";
    }
}

// Генерация CSRF токена
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

ob_start();
?>

<div class="product-details">
    <div class="product-info">
        <h1 class="product-title"><?= htmlspecialchars($product['name']) ?></h1>
        <p><strong>Category:</strong> <?= htmlspecialchars($product['category']) ?></p>
        <p><strong>Price:</strong> $<?= number_format($product['price'], 2) ?>/kg</p>
        <p><strong>Available:</strong> <?= htmlspecialchars($product['quantity']) ?> units</p>
        <p><strong>Description:</strong> <?= nl2br(htmlspecialchars($product['description'])) ?></p>

        <h2>Attributes</h2>
        <ul>
            <?php
            $query = "SELECT Attributes.name, ProductAttributeValues.value 
                  FROM ProductAttributeValues
                  JOIN Attributes ON ProductAttributeValues.attribute_id = Attributes.id
                  WHERE ProductAttributeValues.product_id = :product_id";
            $stmt = $pdo->prepare($query);
            $stmt->execute(['product_id' => $product_id]);
            $attributes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($attributes as $attribute):
                ?>
                <li><strong><?= htmlspecialchars($attribute['name']) ?>:</strong> <?= htmlspecialchars($attribute['value']) ?></li>
            <?php endforeach; ?>
        </ul>

        <?php if ($product['quantity'] > 0): ?>
            <!-- Кнопка для вызова модального окна -->
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addToCartModal">
                Add to Cart
            </button>
        <?php else: ?>
            <p class="text-danger">Out of stock</p>
        <?php endif; ?>
        <?php if (($role == 'admin') || ($role == 'moderator') || ($role == 'farmer')): ?>
            <button class="btn btn-warning mt-3" data-bs-toggle="modal" data-bs-target="#editProductModal">Edit Product</button>
        <?php endif; ?>
    </div>

    <div class="product-gallery">
        <div class="gallery-container">
            <button class="gallery-prev">&#10094;</button>
            <div class="gallery-images">
                <?php if (!empty($product_images)): ?>
                    <?php foreach ($product_images as $image): ?>
                        <img src="<?= htmlspecialchars($image) ?>" alt="Product Image">
                    <?php endforeach; ?>
                <?php else: ?>
                    <img src="../images/placeholder.png" alt="No Image Available" class="placeholder-image">
                <?php endif; ?>
            </div>
            <button class="gallery-next">&#10095;</button>
        </div>
    </div>
</div>

<!-- Модальное окно для добавления в корзину -->
<div class="modal fade" id="addToCartModal" tabindex="-1" aria-labelledby="addToCartModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addToCartModalLabel">Add to Cart</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="add_to_cart.php">
                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                    <div class="mb-3">
                        <label for="quantity" class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="quantity" name="quantity" value="1" min="1" max="<?= $product['quantity'] ?>" required>
                    </div>
                    <button type="submit" class="btn btn-success">Add to Cart</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для редактирования -->
<div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="product.php?id=<?= $product_id ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProductModalLabel">Edit Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                    <!-- Поля основного продукта -->
                    <div class="mb-3">
                        <label for="name" class="form-label">Product Name</label>
                        <input type="text" id="name" name="name" class="form-control" value="<?= htmlspecialchars($product['name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="price" class="form-label">Price</label>
                        <input type="number" id="price" name="price" class="form-control" value="<?= htmlspecialchars($product['price']) ?>" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="quantity" class="form-label">Quantity</label>
                        <input type="number" id="quantity" name="quantity" class="form-control" value="<?= htmlspecialchars($product['quantity']) ?>" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="4" required><?= htmlspecialchars($product['description']) ?></textarea>
                    </div>

                    <!-- Поля текущих изображений -->
                    <div class="mb-3">
                        <label for="current_images" class="form-label">Current Images</label>
                        <div id="current_images">
                            <?php foreach ($product_images as $image_path): ?>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="delete_images[]" value="<?= htmlspecialchars($image_path) ?>">
                                    <label class="form-check-label">
                                        <img src="<?= htmlspecialchars($image_path) ?>" alt="Product Image" style="max-width: 100px;">
                                        Remove this image
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Поля для добавления новых изображений -->
                    <div class="mb-3">
                        <label for="new_images" class="form-label">Add New Images</label>
                        <div id="new_images">
                            <input type="text" class="form-control mb-2" name="new_images[]" placeholder="Enter image path">
                        </div>
                        <button type="button" class="btn btn-secondary btn-sm" id="add_new_image">Add More</button>
                    </div>

                    <!-- Поля атрибутов категории -->
                    <div class="mb-3">
                        <h5>Category Attributes</h5>
                        <?php
                        // Получение атрибутов для категории
                        $query = "SELECT id, name, type, is_required FROM Attributes WHERE category_id = :category_id";
                        $stmt = $pdo->prepare($query);
                        $stmt->execute(['category_id' => $product['category_id']]);
                        $attributes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        // Получение текущих значений атрибутов продукта
                        $query = "SELECT attribute_id, value FROM ProductAttributeValues WHERE product_id = :product_id";
                        $stmt = $pdo->prepare($query);
                        $stmt->execute(['product_id' => $product['id']]);
                        $currentAttributes = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

                        // Отображение полей для атрибутов
                        foreach ($attributes as $attribute):
                            $value = $currentAttributes[$attribute['id']] ?? '';
                            $isRequired = $attribute['is_required'] ? 'required' : '';
                            ?>
                            <div class="mb-3">
                                <label for="attribute_<?= $attribute['id'] ?>" class="form-label"><?= htmlspecialchars($attribute['name']) ?> <?= $attribute['is_required'] ? '*' : '' ?></label>
                                <?php if ($attribute['type'] === 'text'): ?>
                                    <input type="text" class="form-control" id="attribute_<?= $attribute['id'] ?>" name="attributes[<?= $attribute['id'] ?>]" value="<?= htmlspecialchars($value) ?>" <?= $isRequired ?>>
                                <?php elseif ($attribute['type'] === 'number'): ?>
                                    <input type="number" class="form-control" id="attribute_<?= $attribute['id'] ?>" name="attributes[<?= $attribute['id'] ?>]" value="<?= htmlspecialchars($value) ?>" <?= $isRequired ?>>
                                <?php elseif ($attribute['type'] === 'boolean'): ?>
                                    <input type="checkbox" class="form-check-input" id="attribute_<?= $attribute['id'] ?>" name="attributes[<?= $attribute['id'] ?>]" value="1" <?= $value === '1' ? 'checked' : '' ?>>
                                <?php elseif ($attribute['type'] === 'date'): ?>
                                    <input type="date" class="form-control" id="attribute_<?= $attribute['id'] ?>" name="attributes[<?= $attribute['id'] ?>]" value="<?= htmlspecialchars($value) ?>" <?= $isRequired ?>>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Скрипт для добавления новых полей изображений -->
                <script>
                    document.getElementById('add_new_image').addEventListener('click', () => {
                        const newInput = document.createElement('input');
                        newInput.type = 'text';
                        newInput.name = 'new_images[]';
                        newInput.className = 'form-control mb-2';
                        newInput.placeholder = 'Enter image path';
                        document.getElementById('new_images').appendChild(newInput);
                    });
                </script>

                <div class="modal-footer">
                    <button type="submit" name="edit_product" class="btn btn-success">Save Changes</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
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
