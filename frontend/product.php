<?php
session_start();
umask(0022);
require '../backend/db.php';

$role = $_SESSION['user_role'] ?? '';
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$product_id) {
    header('Location: index.php');
    exit;
}

function formatUnit($unit) {
    return str_replace('_', ' ', $unit);
}

function getCategoryAncestors($pdo, $category_id) {
    $categories = [];
    while ($category_id) {
        $stmt = $pdo->prepare("SELECT id, name, parent_id FROM categories WHERE id = :id");
        $stmt->execute(['id' => $category_id]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($category) {
            $categories[] = $category;
            $category_id = $category['parent_id'];
        } else {
            break;
        }
    }
    return $categories;
}

function getAttributesForCategories($pdo, $categories) {
    $category_ids = array_column($categories, 'id');
    if (empty($category_ids)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($category_ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM attributes WHERE category_id IN ($placeholders)");
    $stmt->execute($category_ids);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getProductAttributes($pdo, $product_id) {
    $stmt = $pdo->prepare("
        SELECT a.id, a.name, pa.value
        FROM productattributes pa
        JOIN attributes a ON pa.attribute_id = a.id
        WHERE pa.product_id = :product_id
    ");
    $stmt->execute(['product_id' => $product_id]);
    $attributes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result = [];
    foreach ($attributes as $attr) {
        $result[$attr['id']] = [
            'name' => $attr['name'],
            'value' => $attr['value']
        ];
    }
    return $result;
}

$query = "SELECT products.id, products.name, products.category_id, products.price, products.price_unit, products.quantity, products.quantity_unit, products.description, products.farmer_id, users.name AS farmer_name
          FROM products
          JOIN users ON products.farmer_id = users.id
          WHERE products.id = :product_id";

$stmt = $pdo->prepare($query);
$stmt->execute(['product_id' => $product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo "Product not found!";
    exit;
}

$categories = getCategoryAncestors($pdo, $product['category_id']);

$categories = array_reverse($categories);

$category_path = implode(' / ', array_column($categories, 'name'));

$attributes = getAttributesForCategories($pdo, $categories);

$product_attributes = getProductAttributes($pdo, $product_id);


$user_id = $_SESSION['user_id'] ?? null;
$logged_in = isset($_SESSION['user_id']);
$is_admin_or_moderator = in_array($role, ['admin', 'moderator']);
$is_product_creator = $product['farmer_id'] == $user_id;
$can_edit_product = $is_admin_or_moderator || $is_product_creator;

$images_query = "SELECT id, image_path FROM productimages WHERE product_id = :product_id";
$images_stmt = $pdo->prepare($images_query);
$images_stmt->execute(['product_id' => $product_id]);
$product_images = $images_stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($product_images)) {
    $product_images = [['id' => 0, 'image_path' => '../images/placeholder.png']];
}

$reviews_query = "SELECT AVG(rating) AS average_rating, COUNT(*) AS review_count 
                  FROM reviews 
                  WHERE product_id = :product_id";
$reviews_stmt = $pdo->prepare($reviews_query);
$reviews_stmt->execute(['product_id' => $product_id]);
$reviews_summary = $reviews_stmt->fetch(PDO::FETCH_ASSOC);

$average_rating = $reviews_summary['average_rating'] ? round($reviews_summary['average_rating'], 1) : 0;
$review_count = $reviews_summary['review_count'];

$all_reviews_query = "
    SELECT reviews.id, reviews.rating, reviews.comment, users.id AS user_id, users.name AS user_name
    FROM reviews
    JOIN users ON reviews.user_id = users.id
    WHERE product_id = :product_id
    ORDER BY reviews.id DESC
";


$all_reviews_stmt = $pdo->prepare($all_reviews_query);
$all_reviews_stmt->execute(['product_id' => $product_id]);
$reviews = $all_reviews_stmt->fetchAll(PDO::FETCH_ASSOC);

$categories_query = "SELECT id, name FROM categories ORDER BY name COLLATE utf8mb4_unicode_ci";
$categories_stmt = $pdo->prepare($categories_query);
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contentType = $_SERVER["CONTENT_TYPE"] ?? '';

    if (strpos($contentType, 'application/json') !== false) {
        $data = json_decode(file_get_contents('php://input'), true);
        $csrf_token = $data['csrf_token'] ?? '';
        $action = $data['action'] ?? '';
    } elseif (strpos($contentType, 'multipart/form-data') !== false) {
        $csrf_token = $_POST['csrf_token'] ?? '';
        $action = $_POST['action'] ?? '';
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid content type.']);
        exit;
    }

    if ($csrf_token !== ($_SESSION['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
        exit;
    }

    if ($action === 'delete_product' && $can_edit_product) {
        $pdo->beginTransaction();
        try {
            $image_query = "SELECT image_path FROM productimages WHERE product_id = :product_id";
            $image_stmt = $pdo->prepare($image_query);
            $image_stmt->execute(['product_id' => $product_id]);
            $images = $image_stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($images as $image) {
                if (file_exists($image['image_path'])) {
                    unlink($image['image_path']);
                }
            }

            $delete_images_query = "DELETE FROM productimages WHERE product_id = :product_id";
            $delete_images_stmt = $pdo->prepare($delete_images_query);
            $delete_images_stmt->execute(['product_id' => $product_id]);

            $delete_reviews_query = "DELETE FROM reviews WHERE product_id = :product_id";
            $delete_reviews_stmt = $pdo->prepare($delete_reviews_query);
            $delete_reviews_stmt->execute(['product_id' => $product_id]);

            $delete_product_query = "DELETE FROM products WHERE id = :product_id";
            $delete_product_stmt = $pdo->prepare($delete_product_query);
            $delete_product_stmt->execute(['product_id' => $product_id]);

            $pdo->commit();

            echo json_encode(['success' => true]);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Failed to delete the product.']);
            exit;
        }
    }

    if ($action === 'delete_review') {
        $review_id = (int)($data['review_id'] ?? 0);
    
        // Check that the review exists and belongs to the current user or the deleter is an admin or moderator
        $review_query = "SELECT user_id FROM reviews WHERE id = :review_id AND product_id = :product_id";
        $review_stmt = $pdo->prepare($review_query);
        $review_stmt->execute([
            'review_id' => $review_id,
            'product_id' => $product_id
        ]);
        $review = $review_stmt->fetch(PDO::FETCH_ASSOC);
    
        if ($review && ($is_admin_or_moderator || ($_SESSION['user_id'] == $review['user_id']))) {
            $delete_query = "DELETE FROM reviews WHERE id = :review_id";
            $delete_stmt = $pdo->prepare($delete_query);
            $delete_stmt->execute(['review_id' => $review_id]);
    
            echo json_encode(['success' => true, 'message' => 'Review deleted successfully.']);
            exit;
        } else {
            echo json_encode(['success' => false, 'error' => 'You do not have permission to delete this review.']);
            exit;
        }
    }
    

    if ($action === 'update_product' && $can_edit_product) {
        $name = htmlspecialchars(trim($_POST['name']));
        $price = (float)$_POST['price'];
        $price_unit = $_POST['price_unit'];
        $quantity = (float)$_POST['quantity'];
        $quantity_unit = htmlspecialchars(trim($_POST['quantity_unit']));
        $description = htmlspecialchars(trim($_POST['description']));
        $category_id = (int)$_POST['category_id'];

        $category_exists_query = "SELECT COUNT(*) FROM categories WHERE id = :category_id";
        $category_exists_stmt = $pdo->prepare($category_exists_query);
        $category_exists_stmt->execute(['category_id' => $category_id]);
        $category_exists = $category_exists_stmt->fetchColumn();
    
        if (!$category_exists) {
            echo json_encode(['success' => false, 'error' => 'Invalid category selected.']);
            exit;
        }

        $attributes_input = $_POST['attributes'] ?? [];

        $categories = getCategoryAncestors($pdo, $category_id);
        $attributes = getAttributesForCategories($pdo, $categories);

        $errors = [];
        foreach ($attributes as $attribute) {
            if ($attribute['is_required'] && empty($attributes_input[$attribute['id']])) {
                $errors[] = 'Attribute "' . $attribute['name'] . '" is required.';
            }
        }
    
        if (!empty($errors)) {
            echo json_encode(['success' => false, 'error' => implode(' ', $errors)]);
            exit;
        }
    
        if (!empty($name) && $price > 0 && $quantity >= 0) {
            $pdo->beginTransaction();
            try {
                $update_query = "UPDATE products SET name = :name, category_id = :category_id, price = :price, price_unit = :price_unit, quantity = :quantity, quantity_unit = :quantity_unit, description = :description WHERE id = :product_id";
                $update_stmt = $pdo->prepare($update_query);
                $update_stmt->execute([
                    'name' => $name,
                    'category_id' => $category_id,
                    'price' => $price,
                    'price_unit' => $price_unit,
                    'quantity' => $quantity,
                    'quantity_unit' => $quantity_unit,
                    'description' => $description,
                    'product_id' => $product_id
                ]);

                $delete_attrs_stmt = $pdo->prepare("DELETE FROM productattributes WHERE product_id = :product_id");
                $delete_attrs_stmt->execute(['product_id' => $product_id]);

                $insert_attr_stmt = $pdo->prepare("INSERT INTO productattributes (product_id, attribute_id, value) VALUES (:product_id, :attribute_id, :value)");
                foreach ($attributes as $attribute) {
                    $attr_id = $attribute['id'];
                    $value = $attributes_input[$attr_id] ?? null;
    
                    $insert_attr_stmt->execute([
                        'product_id' => $product_id,
                        'attribute_id' => $attr_id,
                        'value' => $value
                    ]);
                }

                if (!empty($_FILES['images']['name'][0])) {
                    $uploadDir = '../images/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                        chmod($uploadDir, 0755);
                    }
    
                    foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
                        $fileName = basename($_FILES['images']['name'][$key]);
                        $targetFilePath = $uploadDir . $fileName;
    
                        if (move_uploaded_file($tmpName, $targetFilePath)) {
                            chmod($targetFilePath, 0644);
                            $insertImageQuery = "INSERT INTO productimages (product_id, image_path) VALUES (:product_id, :image_path)";
                            $insertImageStmt = $pdo->prepare($insertImageQuery);
                            $insertImageStmt->execute([
                                'product_id' => $product_id,
                                'image_path' => $targetFilePath
                            ]);
                        }
                    }
                }

                $pdo->commit();
    
                echo json_encode(['success' => true, 'message' => 'Product updated successfully.']);
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'error' => 'Failed to update product.']);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Please fill in all required fields.']);
            exit;
        }
    }

    if ($action === 'delete_image' && $can_edit_product) {
        $image_id = (int)($data['image_id'] ?? 0);

        $image_query = "SELECT image_path FROM productimages WHERE id = :image_id AND product_id = :product_id";
        $image_stmt = $pdo->prepare($image_query);
        $image_stmt->execute(['image_id' => $image_id, 'product_id' => $product_id]);
        $image = $image_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($image) {
            $delete_query = "DELETE FROM productimages WHERE id = :image_id";
            $delete_stmt = $pdo->prepare($delete_query);
            $delete_stmt->execute(['image_id' => $image_id]);

            echo json_encode(['success' => true, 'message' => 'Image deleted successfully.']);
            exit;
        } else {
            echo json_encode(['success' => false, 'error' => 'Image not found.']);
            exit;
        }
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

ob_start();
?>

<div class="product-details">
    <div class="product-info">
        <h1 class="product-title"><?= htmlspecialchars($product['name']) ?></h1>
        <?php
        $current_category = $category_path;
        if (strpos($category_path, '/') !== false) {
            $current_category = substr($category_path, strrpos($category_path, '/') + 1);
        }
        ?>

        <p>
            <strong>Category:</strong>
            <?= htmlspecialchars($current_category) ?>
            (<?= htmlspecialchars($category_path) ?>)
        </p>


            <?php if (!empty($attributes)): ?>
                <h6><strong>Attributes:</strong></h6>
                <ul>
                    <?php foreach ($attributes as $attribute): ?>
                        <li>
                            <strong><?= htmlspecialchars($attribute['name']) ?>:</strong>
                            <?= htmlspecialchars($product_attributes[$attribute['id']]['value'] ?? 'N/A') ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <p><strong>Price:</strong> $<?= number_format($product['price'], 2) ?> <?= htmlspecialchars(formatUnit($product['price_unit'])) ?></p>
        <p><strong>Available:</strong> <?= htmlspecialchars($product['quantity']) ?> <?= htmlspecialchars($product['quantity_unit']) ?></p>


        <p><strong>Description:</strong> <?= htmlspecialchars($product['description'] ?? '') ?></p>
        <?php if (!empty($product['farmer_name'])): ?>
            <p><strong>Seller:</strong> <a href="profile.php?id=<?= htmlspecialchars($product['farmer_id']) ?>" style="text-decoration: none; color: inherit;">
                <?= htmlspecialchars($product['farmer_name']) ?></a></p>
        <?php else: ?>
            <p><strong>Seller:</strong> Unknown</p>
        <?php endif; ?>

        <?php if ($product['quantity'] > 0): ?>
            <?php if ($logged_in): ?>
                <?php if ($product['farmer_id'] !== $user_id): ?>
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addToCartModal">
                        Add to Cart
                    </button>
                <?php else: ?>
                    <p class="text-warning">You cannot add your own product to the cart.</p>
                <?php endif; ?>
            <?php else: ?>
                <p class="text-dark text-center">
                    <span class="d-inline-block bg-light p-3 rounded shadow-sm" style="border: 1px solid #ccc; max-width: 300px;">
                        Please
                        <a href="#" class="btn btn-primary btn-sm text-light" style="text-decoration: none;" data-bs-toggle="modal" data-bs-target="#loginModal">
                            Log in
                        </a>
                        to add products to your cart.
                    </span>
                </p>
            <?php endif; ?>
        <?php else: ?>
            <p class="text-danger">Out of stock</p>
        <?php endif; ?>

        <?php if ($can_edit_product): ?>
            <button class="btn btn-warning mt-3" data-bs-toggle="modal" data-bs-target="#editProductModal">Edit Product</button>
            <button class="btn btn-danger mt-3" id="deleteProductButton">Delete Product</button>
        <?php endif; ?>

    </div>


    <div class="product-gallery">
        <div class="product-gallery-container">
            <div class="product-gallery-images">
                <?php if (!empty($product_images)): ?>
                    <?php foreach ($product_images as $image): ?>
                        <img src="<?= htmlspecialchars($image['image_path']) ?>" alt="Product Image">
                    <?php endforeach; ?>
                <?php else: ?>
                    <img src="../images/placeholder.png" alt="No Image Available" class="placeholder-image">
                <?php endif; ?>
            </div>
            <?php if (count($product_images) > 1): ?>
                <button class="product-gallery-prev">&#10094;</button>
                <button class="product-gallery-next">&#10095;</button>
            <?php endif; ?>
        </div>
    </div>



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
                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                    <div class="mb-3">
                        <label for="quantity" class="form-label">Quantity (<?= htmlspecialchars($product['quantity_unit']) ?>)</label>
                        <input type="number" class="form-control" id="quantity" name="quantity" value="1" min="1" max="<?= $product['quantity'] ?>" required>
                    </div>
                    <button type="submit" class="btn btn-success">Add to Cart</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="editProductForm" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProductModalLabel">Edit Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="productName" class="form-label">Product Name</label>
                        <input type="text" class="form-control" id="productName" name="name" value="<?= htmlspecialchars($product['name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="productPrice" class="form-label">Price</label>
                        <input type="number" class="form-control" id="productPrice" name="price" value="<?= htmlspecialchars($product['price']) ?>" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="priceUnit" class="form-label">Price Unit</label>
                        <select class="form-control" id="priceUnit" name="price_unit" required>
                            <option value="per_unit" <?= ($product['price_unit'] == 'per_unit') ? 'selected' : '' ?>>Per Unit</option>
                            <option value="per_kg" <?= ($product['price_unit'] == 'per_kg') ? 'selected' : '' ?>>Per Kg</option>
                            <option value="per_liter" <?= ($product['price_unit'] == 'per_liter') ? 'selected' : '' ?>>Per Liter</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="productQuantity" class="form-label">Quantity</label>
                        <input
                                type="number"
                                class="form-control"
                                id="productQuantity"
                                name="quantity"
                                value="<?= htmlspecialchars($product['quantity']) ?>"
                                min="0"
                                required
                                step="<?= $product['price_unit'] === 'per_unit' ? '1' : '0.01' ?>">

                    </div>
                    <div class="mb-3">
                        <label for="quantityUnit" class="form-label">Quantity Unit</label>
                        <input type="text" class="form-control" id="quantityUnit" name="quantity_unit" value="<?= htmlspecialchars($product['quantity_unit']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="productCategory" class="form-label">Category</label>
                        <select class="form-control" id="productCategory" name="category_id" required>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>" <?= $category['id'] == $product['category_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="attributesContainer">
                        <?php if (!empty($attributes)): ?>
                            <h4>Attributes</h4>
                            <?php foreach ($attributes as $attribute): ?>
                                <div class="mb-3">
                                    <label for="attribute_<?= $attribute['id'] ?>" class="form-label">
                                        <?= htmlspecialchars($attribute['name']) ?>
                                        <?php if ($attribute['is_required']): ?><span class="text-danger">*</span><?php endif; ?>
                                    </label>
                                    <?php
                                    $value = $product_attributes[$attribute['id']]['value'] ?? '';
                                    $inputType = $attribute['type'] === 'number' ? 'number' : ($attribute['type'] === 'date' ? 'date' : 'text');
                                    ?>
                                    <input
                                        type="<?= $inputType ?>"
                                        class="form-control"
                                        id="attribute_<?= $attribute['id'] ?>"
                                        name="attributes[<?= $attribute['id'] ?>]"
                                        value="<?= htmlspecialchars($value) ?>"
                                        <?= $attribute['is_required'] ? 'required' : '' ?>
                                    >
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="productDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="productDescription" name="description" rows="4" required><?= htmlspecialchars($product['description']) ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Attached Images</label>
                        <div id="existingProductImages" class="d-flex flex-wrap">
                            <?php foreach ($product_images as $image): ?>
                                <div class="position-relative m-2">
                                    <img src="<?= htmlspecialchars($image['image_path']) ?>" alt="Product Image" class="img-thumbnail" style="max-width: 150px;">
                                    <?php if ($image['id'] != 0): ?>
                                        <button type="button" class="btn btn-danger btn-sm delete-image-btn position-absolute top-0 end-0" data-image-id="<?= htmlspecialchars($image['id']) ?>">&times;</button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Add New Images</label>
                        <div id="imageUploadArea" class="border p-3 text-center">
                            <p>Drag and drop images here or click to select files</p>
                            <input type="file" id="imageInput" name="images[]" multiple hidden>
                        </div>
                        <div id="imagePreview" class="d-flex flex-wrap mt-3">

                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="update_product">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const editProductForm = document.getElementById('editProductForm');
    const imageUploadArea = document.getElementById('imageUploadArea');
    const imageInput = document.getElementById('imageInput');
    const imagePreview = document.getElementById('imagePreview');
    const existingProductImages = document.getElementById('existingProductImages');

    imageUploadArea.addEventListener('click', () => imageInput.click());
    imageUploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        imageUploadArea.classList.add('dragover');
    });
    imageUploadArea.addEventListener('dragleave', () => imageUploadArea.classList.remove('dragover'));
    imageUploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        imageUploadArea.classList.remove('dragover');
        imageInput.files = e.dataTransfer.files;
        previewImages();
    });
    imageInput.addEventListener('change', previewImages);

    function previewImages() {
        imagePreview.innerHTML = '';
        const files = imageInput.files;
        for (const file of files) {
            const reader = new FileReader();
            reader.onload = (e) => {
                const imgDiv = document.createElement('div');
                imgDiv.classList.add('position-relative', 'm-2');
                const img = document.createElement('img');
                img.src = e.target.result;
                img.classList.add('img-thumbnail');
                img.style.maxWidth = '150px';
                imgDiv.appendChild(img);
                imagePreview.appendChild(imgDiv);
            };
            reader.readAsDataURL(file);
        }
    }

    existingProductImages.addEventListener('click', (e) => {
        if (e.target.classList.contains('delete-image-btn')) {
            const imageId = e.target.dataset.imageId;
            if (confirm('Are you sure you want to delete this image?')) {
                fetch('product.php?id=<?= $product_id ?>', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'delete_image',
                        csrf_token: '<?= $_SESSION['csrf_token'] ?>',
                        image_id: imageId
                    }),
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove the image element from the DOM
                        e.target.parentElement.remove();
                    } else {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        }
    });

    editProductForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const formData = new FormData(editProductForm);

        fetch('product.php?id=<?= $product_id ?>', {
            method: 'POST',
            body: formData,
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Product successfully updated.');
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(error => console.error('Error:', error));
    });

    const deleteProductButton = document.getElementById('deleteProductButton');
    if (deleteProductButton) {
        deleteProductButton.addEventListener('click', () => {
            if (confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
                fetch('product.php?id=<?= $product_id ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'delete_product',
                        csrf_token: '<?= $_SESSION['csrf_token'] ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Product deleted successfully.');
                        window.location.href = 'index.php';
                    } else {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        });
    }

    const categorySelect = document.getElementById('productCategory');
    const attributesContainer = document.getElementById('attributesContainer');

    categorySelect.addEventListener('change', () => {
        const categoryId = categorySelect.value;

        fetch('get_category_attributes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ category_id: categoryId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                attributesContainer.innerHTML = data.html;
            } else {
                alert('Error fetching attributes: ' + data.error);
            }
        })
        .catch(error => console.error('Error:', error));
    });

    const addToCartModal = document.getElementById('addToCartModal');
    const quantityInput = addToCartModal.querySelector('#quantity');

    const setQuantityStep = (priceUnit) => {
        if (priceUnit === 'per_unit') {
            quantityInput.setAttribute('step', '1');
        } else {
            quantityInput.setAttribute('step', '0.01');
        }
    };

    const addToCartButton = document.querySelector('button[data-bs-target="#addToCartModal"]');
    if (addToCartButton) {
        addToCartButton.addEventListener('click', (event) => {
            const priceUnit = '<?= htmlspecialchars($product['price_unit']) ?>';
            setQuantityStep(priceUnit);
        });
    }
});
</script>

<div class="product-rating">
    <p>
        <strong>Average Rating:</strong> <?= $average_rating ?> / 5
        <span class="stars">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <?php if ($i <= floor($average_rating)): ?>
                    <i class="fa fa-star"></i>
                <?php elseif ($i - $average_rating <= 0.5): ?>
                    <i class="fa fa-star-half-alt"></i>
                <?php else: ?>
                    <i class="fa fa-star-o"></i>
                <?php endif; ?>
            <?php endfor; ?>
        </span>
        (<?= $review_count ?> reviews)
    </p>
</div>

<div class="product-reviews">
    <h2>reviews</h2>
    <?php if ($reviews): ?>
        <?php foreach ($reviews as $review): ?>
            <div class="review d-flex justify-content-between align-items-start">
                <div>
                    <p>
                        <strong>By:</strong>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="profile.php?id=<?= htmlspecialchars($review['user_id']) ?>" style="text-decoration: none; color: inherit;">
                                <?= htmlspecialchars($review['user_name']) ?>
                            </a>
                        <?php else: ?>
                            <span style="text-decoration: none; color: inherit;">
                                <?= htmlspecialchars($review['user_name']) ?>
                            </span>
                        <?php endif; ?>
                        <strong>Rating:</strong> <?= str_repeat('⭐', (int)$review['rating']) ?>
                    </p>
                    <p><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
                </div>
                <?php if ($is_admin_or_moderator || ($_SESSION['user_id'] ?? null) == $review['user_id']): ?>
                    <button
                        class="btn btn-danger btn-sm delete-review-btn"
                        data-review-id="<?= htmlspecialchars($review['id']) ?>"
                        style="margin-left: 10px;"
                    >
                        Delete
                    </button>
                <?php endif; ?>
            </div>
            <hr>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No reviews yet. Be the first to leave a review!</p>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.delete-review-btn').forEach(button => {
        button.addEventListener('click', () => {
            const reviewId = button.dataset.reviewId;

            if (confirm('Are you sure you want to delete this review?')) {
                fetch('product.php?id=<?= $product_id ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'delete_review',
                        review_id: reviewId,
                        csrf_token: '<?= $_SESSION['csrf_token'] ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Review deleted successfully.');
                        button.closest('.review').remove();
                    } else {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        });
    });
});

</script>

<?php
$content = ob_get_clean();
require '../interface/templates/layout.php';
?>
