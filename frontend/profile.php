<?php
session_start();
require '../backend/db.php';
require '../backend/auth.php';

$is_logged_in = isset($_SESSION['user_id']);
$current_user_id = $_SESSION['user_id'] ?? null;
$current_user_role = $_SESSION['user_role'] ?? null;

// ID профиля для отображения
$viewed_user_id = isset($_GET['id']) ? (int)$_GET['id'] : ($is_logged_in ? $current_user_id : null);

// Если ID профиля не указан и пользователь не залогинен
if (!$viewed_user_id) {
    echo "Profile not found.";
    exit;
}

// Получение данных пользователя
$query = "SELECT id, name, email, phone, photo_path, role FROM users WHERE id = :user_id";
$stmt = $pdo->prepare($query);
$stmt->execute(['user_id' => $viewed_user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "User not found.";
    exit;
}

// Проверка, просматривается ли свой профиль
$is_own_profile = $viewed_user_id === $current_user_id;
$is_admin = $current_user_role === 'admin';
$is_moderator = $current_user_role === 'moderator';
$can_edit_profile = $is_own_profile || $is_admin;

// Проверка, может ли пользователь предлагать ивенты
$roles_allowed_to_propose_event = ['farmer', 'moderator', 'admin'];
$is_allowed_to_propose_event = in_array($current_user_role, $roles_allowed_to_propose_event);

// Проверка, может ли пользователь удалить аккаунт
$can_delete_account = false;
if ($is_own_profile) {
    if (!in_array($current_user_role, ['admin', 'moderator'])) {
        $can_delete_account = true;
    }
} else {
    if ($is_admin) {
        $can_delete_account = true;
    }
}

// Получение списка продуктов фермера
if ($user['role'] === 'farmer' || $is_admin) {
    $products_query = "SELECT id, name, price, quantity FROM products WHERE farmer_id = :farmer_id ORDER BY name ASC";
    $products_stmt = $pdo->prepare($products_query);
    $products_stmt->execute(['farmer_id' => $viewed_user_id]);
    $products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Получение списка заказов (только если это свой профиль)
if ($is_own_profile) {
    $orders_query = "SELECT o.id, o.status, o.order_date, SUM(oi.quantity * oi.price_per_unit) AS total_price
                     FROM orders o
                     JOIN orderitems oi ON o.id = oi.order_id
                     WHERE o.customer_id = :user_id
                     GROUP BY o.id
                     ORDER BY o.order_date DESC";
    $orders_stmt = $pdo->prepare($orders_query);
    $orders_stmt->execute(['user_id' => $viewed_user_id]);
    $orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Получение списка интересующих ивентов (только для собственного профиля)
$interested_events = [];
if ($is_own_profile) {
    $events_query = "SELECT e.id, e.name, e.date, e.location 
                     FROM userinterests ui
                     JOIN events e ON ui.event_id = e.id
                     WHERE ui.user_id = :user_id
                     ORDER BY e.date ASC";
    $events_stmt = $pdo->prepare($events_query);
    $events_stmt->execute(['user_id' => $current_user_id]);
    $interested_events = $events_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Генерация CSRF токена
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Получение статистики
// Количество заказов
$orders_count_query = "SELECT COUNT(*) FROM orders WHERE customer_id = :user_id";
$orders_count_stmt = $pdo->prepare($orders_count_query);
$orders_count_stmt->execute(['user_id' => $viewed_user_id]);
$orders_count = $orders_count_stmt->fetchColumn();

// Количество отзывов
$reviews_count_query = "SELECT COUNT(*) FROM reviews WHERE user_id = :user_id";
$reviews_count_stmt = $pdo->prepare($reviews_count_query);
$reviews_count_stmt->execute(['user_id' => $viewed_user_id]);
$reviews_count = $reviews_count_stmt->fetchColumn();

// Обработка редактирования профиля
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_profile' && $can_edit_profile) {
    $csrf_token = $_POST['csrf_token'] ?? '';

    // Проверка CSRF токена
    if ($csrf_token !== ($_SESSION['csrf_token'] ?? '')) {
        die('Invalid CSRF token.');
    }

    $name = htmlspecialchars(trim($_POST['name']));
    $phone = htmlspecialchars(trim($_POST['phone']));

    if (empty($name)) {
        $error = 'Name cannot be empty.';
    } else {
        // Обновление данных пользователя
        $update_query = "UPDATE users SET name = :name, phone = :phone WHERE id = :user_id";
        $update_stmt = $pdo->prepare($update_query);
        $update_stmt->execute([
            'name' => $name,
            'phone' => $phone,
            'user_id' => $viewed_user_id
        ]);

        // Обработка загрузки фотографии
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../images/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $tmpName = $_FILES['photo']['tmp_name'];
            $fileName = basename($_FILES['photo']['name']);
            $targetFilePath = $uploadDir . $fileName;

            if (move_uploaded_file($tmpName, $targetFilePath)) {
                // Удаляем старую фотографию, если она была
                if (!empty($user['photo_path']) && file_exists($user['photo_path'])) {
                    unlink($user['photo_path']);
                }

                // Обновляем путь к фотографии в базе данных
                $update_photo_query = "UPDATE users SET photo_path = :photo_path WHERE id = :user_id";
                $update_photo_stmt = $pdo->prepare($update_photo_query);
                $update_photo_stmt->execute([
                    'photo_path' => $targetFilePath,
                    'user_id' => $viewed_user_id
                ]);
            } else {
                $error = 'Failed to upload photo.';
            }
        }

        $success = 'Profile updated successfully.';
        // Обновляем информацию о пользователе
        header("Location: profile.php?id=$viewed_user_id");
        exit;
    }
}

// Обработка удаления аккаунта
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_account' && $can_delete_account) {
    $csrf_token = $_POST['csrf_token'] ?? '';

    // Проверка CSRF токена
    if ($csrf_token !== ($_SESSION['csrf_token'] ?? '')) {
        die('Invalid CSRF token.');
    }

    // Удаление аккаунта
    // Начинаем транзакцию
    $pdo->beginTransaction();
    try {
        // Удаляем связанные данные (например, продукты, заказы и т.д.)
        // Это необходимо реализовать в зависимости от структуры вашей базы данных

        // Удаляем пользователя
        $delete_user_query = "DELETE FROM users WHERE id = :user_id";
        $delete_user_stmt = $pdo->prepare($delete_user_query);
        $delete_user_stmt->execute(['user_id' => $viewed_user_id]);

        $pdo->commit();

        // Если пользователь удалил свой собственный аккаунт, разлогиниваем его
        if ($is_own_profile) {
            session_destroy();
            header('Location: login.php');
        } else {
            header('Location: index.php');
        }
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Failed to delete account.';
    }
}

ob_start();
?>

<div class="profile">
    <h1 class="text-center"><?= $is_own_profile ? 'Your Profile' : htmlspecialchars($user['name']) . "'s Profile" ?></h1>

    <!-- Основные данные пользователя -->
    <div class="d-flex">
        <div class="profile-info w-50">
            <p><strong>Name:</strong> <?= htmlspecialchars($user['name']) ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
            <p><strong>Phone:</strong> <?= htmlspecialchars($user['phone'] ?? 'Not provided') ?></p>
            <p><strong>Role:</strong> <?= htmlspecialchars(ucfirst($user['role'])) ?></p>
        </div>
        <div class="profile-photo w-50 text-end">
            <img src="<?= htmlspecialchars($user['photo_path'] ?? '../images/placeholder.png') ?>" alt="Profile Photo" style="max-height: 200px; width: auto; border-radius: 10px;">
            <!-- Мини-статистика -->
            <div class="mini-stats mt-3">
                <p><strong>Orders Made:</strong> <?= $orders_count ?></p>
                <p><strong>Reviews Left:</strong> <?= $reviews_count ?></p>
            </div>
        </div>
    </div>

    <!-- Кнопка подачи заявки на фермерство -->
    <?php
    // Проверка на существующую заявку
    $has_pending_application = false;
    if ($is_own_profile && $user['role'] === 'customer') {
        $check_query = "SELECT * FROM farmerapplications WHERE user_id = :user_id AND status = 'pending'";
        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->execute(['user_id' => $current_user_id]);
        $existing_application = $check_stmt->fetch(PDO::FETCH_ASSOC);

        $has_pending_application = !empty($existing_application);
    }
    ?>

    <?php if ($is_own_profile && $user['role'] === 'customer'): ?>
        <?php if ($has_pending_application): ?>
            <button class="btn btn-secondary mt-3" disabled>
                Application Pending
            </button>
        <?php else: ?>
            <a href="apply_farmer.php" class="btn btn-success mt-3">
                Apply to Become a Farmer
            </a>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Кнопка предложения ивента -->
    <?php if ($is_own_profile && $is_allowed_to_propose_event): ?>
        <a href="apply_event.php" class="btn btn-primary mt-3">
            Propose Event
        </a>
    <?php endif; ?>

    <!-- Кнопка подачи предложения категории -->
    <?php if ($is_own_profile): ?>
        <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#categoryProposalModal">
            Propose Category
        </button>
    <?php endif; ?>

    <!-- Модальное окно для ввода названия категории -->
    <?php if ($is_own_profile): ?>
        <div class="modal fade" id="categoryProposalModal" tabindex="-1" aria-labelledby="categoryProposalModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="profile.php">
                        <div class="modal-header">
                            <h5 class="modal-title" id="categoryProposalModalLabel">Propose New Category</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <div class="mb-3">
                                <label for="category_name" class="form-label">Category Name</label>
                                <input type="text" class="form-control" id="category_name" name="category_name" required maxlength="255">
                                <small class="form-text text-muted">Provide the name of the category you want to propose.</small>
                            </div>
                            <div class="mb-3">
                                <label for="parent_category" class="form-label">Parent Category (Optional)</label>
                                <select class="form-control" id="parent_category" name="parent_category">
                                    <option value="">No Parent</option>
                                    <?php
                                    // Получение всех категорий для выбора родительской
                                    $categories_query = "SELECT id, name FROM Categories";
                                    $categories_stmt = $pdo->query($categories_query);
                                    while ($category = $categories_stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo '<option value="' . htmlspecialchars($category['id']) . '">' . htmlspecialchars($category['name']) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" name="propose_category" class="btn btn-primary">Submit Proposal</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Кнопка редактирования профиля -->
    <?php if ($can_edit_profile): ?>
        <button class="btn btn-warning mt-3" data-bs-toggle="modal" data-bs-target="#editProfileModal">
            Edit Profile
        </button>
    <?php endif; ?>

    <!-- Кнопка удаления аккаунта -->
    <?php if ($can_delete_account): ?>
        <button class="btn btn-danger mt-3" id="deleteAccountButton">
            Delete Account
        </button>
    <?php endif; ?>

    <!-- Список продуктов -->
    <?php if (($user['role'] === 'farmer' || $is_admin) && !empty($products)): ?>
        <div class="products mt-4">
            <h2><?= $is_own_profile ? 'Your Products' : htmlspecialchars($user['name']) . "'s Products" ?></h2>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <?php if ($is_own_profile || $is_admin): ?>
                            <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td>
                                <a href="product.php?id=<?= htmlspecialchars($product['id']) ?>">
                                    <?= htmlspecialchars($product['name']) ?>
                                </a>
                            </td>
                            <td>$<?= number_format($product['price'], 2) ?></td>
                            <td><?= htmlspecialchars($product['quantity']) ?></td>
                            <?php if ($is_own_profile || $is_admin): ?>
                                <td>
                                    <!-- Добавьте действия по редактированию или удалению продукта -->
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <!-- Кнопка добавления продукта -->
            <?php if ($is_own_profile || $is_admin): ?>
                <button class="btn btn-success mt-3" data-bs-toggle="modal" data-bs-target="#addProductModal">
                    Add New Product
                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Список заказов (только для собственного профиля) -->
    <?php if ($is_own_profile): ?>
        <div class="orders mt-4">
            <h2>Your Orders</h2>
            <?php if (!empty($orders)): ?>
                <div id="ordersList" class="overflow-auto" style="max-height: 300px;">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Total Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><?= htmlspecialchars($order['id']) ?></td>
                                    <td><?= htmlspecialchars(ucfirst($order['status'])) ?></td>
                                    <td><?= htmlspecialchars(date('F j, Y', strtotime($order['order_date']))) ?></td>
                                    <td>$<?= number_format($order['total_price'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>You have not placed any orders yet.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Список интересующих ивентов (только для собственного профиля) -->
    <?php if ($is_own_profile): ?>
        <div class="interested-events mt-4">
            <h2>Your Interested Events</h2>
            <?php if (!empty($interested_events)): ?>
                <div id="interestedEventsList" class="overflow-auto" style="max-height: 300px;">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Event Name</th>
                                <th>Date</th>
                                <th>Location</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($interested_events as $event): ?>
                                <tr>
                                    <td>
                                        <a href="event.php?id=<?= htmlspecialchars($event['id']) ?>">
                                            <?= htmlspecialchars($event['name']) ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars(date('F j, Y', strtotime($event['date']))) ?></td>
                                    <td><?= htmlspecialchars($event['location']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>You have not added any events to your interests yet.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Модальное окно для редактирования профиля -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editProfileForm" method="POST" action="profile.php?id=<?= $viewed_user_id ?>" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProfileModalLabel">Edit Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="edit_profile">
                    <div class="mb-3">
                        <label for="profileName" class="form-label">Name</label>
                        <input type="text" class="form-control" id="profileName" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="profilePhone" class="form-label">Phone</label>
                        <input type="text" class="form-control" id="profilePhone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                    </div>
                    <!-- Photo upload -->
                    <div class="mb-3">
                        <label class="form-label">Profile Photo</label>
                        <div id="photoUploadArea" class="border p-3 text-center">
                            <p>Drag and drop a photo here or click to select a file</p>
                            <input type="file" id="photoInput" name="photo" hidden>
                        </div>
                        <!-- Photo Preview -->
                        <div id="photoPreview" class="mt-3 text-center">
                            <?php if (!empty($user['photo_path'])): ?>
                                <img src="<?= htmlspecialchars($user['photo_path']) ?>" alt="Profile Photo" class="img-thumbnail" style="max-height: 200px;">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Delete Account
    const deleteAccountButton = document.getElementById('deleteAccountButton');
    if (deleteAccountButton) {
        deleteAccountButton.addEventListener('click', () => {
            if (confirm('Are you sure you want to delete this account? This action cannot be undone.')) {
                const formData = new FormData();
                formData.append('action', 'delete_account');
                formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');

                fetch('profile.php?id=<?= $viewed_user_id ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (response.redirected) {
                        window.location.href = response.url;
                    } else {
                        return response.text();
                    }
                })
                .then(data => {
                    if (data) {
                        alert(data);
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        });
    }

    // Photo upload
    const photoUploadArea = document.getElementById('photoUploadArea');
    const photoInput = document.getElementById('photoInput');
    const photoPreview = document.getElementById('photoPreview');

    photoUploadArea.addEventListener('click', () => photoInput.click());
    photoUploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        photoUploadArea.classList.add('dragover');
    });
    photoUploadArea.addEventListener('dragleave', () => photoUploadArea.classList.remove('dragover'));
    photoUploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        photoUploadArea.classList.remove('dragover');
        photoInput.files = e.dataTransfer.files;
        previewPhoto();
    });
    photoInput.addEventListener('change', previewPhoto);

    function previewPhoto() {
        photoPreview.innerHTML = '';
        const file = photoInput.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.classList.add('img-thumbnail');
                img.style.maxHeight = '200px';
                photoPreview.appendChild(img);
            };
            reader.readAsDataURL(file);
        }
    }
});
</script>

<?php
$content = ob_get_clean();
require '../interface/templates/layout.php';
?>
