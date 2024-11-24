<?php
session_start();
require '../backend/db.php';

$title = isset($event['name']) ? htmlspecialchars($event['name']) : 'Event Details';

// Проверяем, есть ли `event_id` в параметрах
$event_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$event_id) {
    header('Location: events.php');
    exit;
}

// Получаем данные события
$query = "
    SELECT events.id, events.name, events.location, events.date, events.description, 
           events.organizer_id, users.name AS organizer 
    FROM events
    LEFT JOIN Users ON events.organizer_id = Users.id
    WHERE events.id = :event_id
";
$stmt = $pdo->prepare($query);
$stmt->execute(['event_id' => $event_id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

// Если событие не найдено
if (!$event) {
    $error = "Event not found.";
}

// Получаем изображения события
$image_query = "SELECT image_path FROM eventimages WHERE event_id = :event_id";
$image_stmt = $pdo->prepare($image_query);
$image_stmt->execute(['event_id' => $event_id]);
$event_images = $image_stmt->fetchAll(PDO::FETCH_COLUMN);

// Если нет изображений, используем placeholder
if (empty($event_images)) {
    $event_images = ['../images/placeholder.png'];
}

// Определяем статус события
function getEventStatus($date) {
    $currentDate = date('Y-m-d');
    if ($date > $currentDate) {
        return "Upcoming";
    } elseif ($date === $currentDate) {
        return "Ongoing";
    } else {
        return "Completed";
    }
}

$event_status = getEventStatus($event['date'] ?? '');

// Проверяем роли
$logged_in = isset($_SESSION['user_id']);
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? null;
$is_admin_or_moderator = in_array($user_role, ['admin', 'moderator']);

// Инициализируем переменную $is_interested
$is_interested = false;

// Проверяем, добавлено ли событие в интересы
if ($logged_in) {
    $query = "SELECT id FROM userinterests WHERE user_id = :user_id AND event_id = :event_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['user_id' => $user_id, 'event_id' => $event_id]);
    $is_interested = $stmt->rowCount() > 0;
}

// Обновление события
// Обновление события, включая изображения
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_event']) && $is_admin_or_moderator) {
    $csrf_token = $_POST['csrf_token'] ?? '';

    if ($csrf_token !== ($_SESSION['csrf_token'] ?? '')) {
        die('Invalid CSRF token.');
    }

    $name = htmlspecialchars(trim($_POST['name']));
    $location = htmlspecialchars(trim($_POST['location']));
    $date = $_POST['date'];
    $description = htmlspecialchars(trim($_POST['description']));
    $new_images = $_POST['new_images'] ?? [];
    $delete_images = $_POST['delete_images'] ?? [];

    if (!empty($name) && !empty($location) && !empty($date)) {
        $query = "UPDATE events SET name = :name, location = :location, date = :date, description = :description WHERE id = :event_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            'name' => $name,
            'location' => $location,
            'date' => $date,
            'description' => $description,
            'event_id' => $event_id
        ]);

        // Удаление выбранных изображений
        if (!empty($delete_images)) {
            $delete_query = "DELETE FROM eventimages WHERE event_id = :event_id AND image_path = :image_path";
            $delete_stmt = $pdo->prepare($delete_query);
            foreach ($delete_images as $image) {
                $image_path = htmlspecialchars(trim($image));
                if (file_exists($image_path) && is_file($image_path)) {
                    unlink($image_path); // Удаляем файл с сервера
                }
                $delete_stmt->execute([
                    'event_id' => $event_id,
                    'image_path' => $image_path,
                ]);
            }
        }
        

        // Добавление новых изображений
        if (!empty($new_images)) {
            $insert_query = "INSERT INTO eventimages (event_id, image_path) VALUES (:event_id, :image_path)";
            $insert_stmt = $pdo->prepare($insert_query);
            foreach ($new_images as $image) {
                $image_path = htmlspecialchars(trim($image));
                if (file_exists($image_path) && is_file($image_path)) { // Проверяем существование файла и что это файл
                    $insert_stmt->execute([
                        'event_id' => $event_id,
                        'image_path' => $image_path,
                    ]);
                } else {
                    $error = "The image path '$image_path' does not exist or is not a valid file.";
                }
            }
        }
        
        

        header("Location: event.php?id=$event_id");
        exit;
    } else {
        $error = "Please fill in all fields.";
    }
}


// Генерация CSRF токена
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

ob_start();
?>
<h1 class="text-center mb-4"><?= htmlspecialchars($event['name'] ?? 'Event Details') ?></h1>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php else: ?>
    <!-- Hero-секция слайдера -->
    <div class="hero-slider">
        <div class="slider-container">
            <?php foreach ($event_images as $image_path): ?>
                <div class="slider-item">
                    <img src="<?= htmlspecialchars($image_path) ?>" alt="Event Image">
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (count($event_images) > 1): ?>
            <button class="slider-prev">&#10094;</button>
            <button class="slider-next">&#10095;</button>
        <?php endif; ?>
    </div>

    <div class="event-details">
        <p><strong>Location:</strong> <?= htmlspecialchars($event['location']) ?></p>
        <p><strong>Date:</strong> <?= htmlspecialchars(date('F j, Y', strtotime($event['date']))) ?></p>
        <p><strong>Organizer:</strong> <?= htmlspecialchars($event['organizer']) ?></p>
        <p><strong>Status:</strong> 
            <span class="<?= $event_status === 'Completed' ? 'text-danger' : ($event_status === 'Ongoing' ? 'text-warning' : 'text-success') ?>">
                <?= $event_status ?>
            </span>
        </p>
        <?php if (!empty($event['description'])): ?>
            <div class="event-description mt-4">
                <h3>Description</h3>
                <p><?= nl2br(htmlspecialchars($event['description'])) ?></p>
            </div>
        <?php else: ?>
            <p class="text-muted">No description available for this event.</p>
        <?php endif; ?>

        <?php if ($logged_in): ?>
            <form method="POST" action="event.php?id=<?= $event_id ?>">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <?php if (!$is_interested): ?>
                    <button type="submit" name="add_to_interests" class="btn btn-primary">Add to Interests</button>
                <?php else: ?>
                    <button type="submit" name="remove_from_interests" class="btn btn-danger">Remove from Interests</button>
                <?php endif; ?>
            </form>
        <?php endif; ?>

        <?php if ($is_admin_or_moderator): ?>
            <button class="btn btn-warning mt-3" data-bs-toggle="modal" data-bs-target="#editEventModal">Edit Event</button>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Модальное окно для редактирования -->
<?php if ($is_admin_or_moderator): ?>
    <div class="modal fade" id="editEventModal" tabindex="-1" aria-labelledby="editEventModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="event.php?id=<?= $event_id ?>">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editEventModalLabel">Edit Event</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <div class="mb-3">
                            <label for="name" class="form-label">Event Name</label>
                            <input type="text" id="name" name="name" class="form-control" value="<?= htmlspecialchars($event['name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="location" class="form-label">Location</label>
                            <input type="text" id="location" name="location" class="form-control" value="<?= htmlspecialchars($event['location']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="date" class="form-label">Date</label>
                            <input type="date" id="date" name="date" class="form-control" value="<?= htmlspecialchars($event['date']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="4" required><?= htmlspecialchars($event['description']) ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="current_images" class="form-label">Current Images</label>
                            <div id="current_images" class="mb-3">
                                <?php foreach ($event_images as $image_path): ?>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" name="delete_images[]" value="<?= htmlspecialchars($image_path) ?>" id="delete_<?= md5($image_path) ?>">
                                        <label class="form-check-label" for="delete_<?= md5($image_path) ?>">
                                            <img src="<?= htmlspecialchars($image_path) ?>" alt="Event Image" style="max-width: 100px; margin-right: 10px;">
                                            Remove this image
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="new_images" class="form-label">Add New Images</label>
                            <div id="new_images">
                                <input type="text" class="form-control mb-2" name="new_images[]" placeholder="Enter image path">
                            </div>
                            <button type="button" id="add_new_image" class="btn btn-secondary btn-sm">Add Another Image</button>
                        </div>
                    </div>
                    <script>
                        document.addEventListener('DOMContentLoaded', () => {
                            const addNewImageBtn = document.getElementById('add_new_image');
                            const newImagesContainer = document.getElementById('new_images');

                            addNewImageBtn.addEventListener('click', () => {
                                const newInput = document.createElement('input');
                                newInput.type = 'text';
                                newInput.name = 'new_images[]';
                                newInput.className = 'form-control mb-2';
                                newInput.placeholder = 'Enter image path';
                                newInput.addEventListener('blur', () => {
                                    if (!newInput.value.startsWith('/path/to/images/')) {
                                        alert('Please ensure the image path is valid.');
                                    }
                                });
                                newImagesContainer.appendChild(newInput);
                            });
                        });

                    </script>

                    <div class="modal-footer">
                        <button type="submit" name="edit_event" class="btn btn-success">Save Changes</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const sliderContainer = document.querySelector('.slider-container');
        const sliderItems = document.querySelectorAll('.slider-item');
        const prevButton = document.querySelector('.slider-prev');
        const nextButton = document.querySelector('.slider-next');
        let currentIndex = 0;

        function updateSlider() {
            const offset = -currentIndex * 100; // Смещение контейнера
            sliderContainer.style.transform = `translateX(${offset}%)`;
        }

        if (prevButton && nextButton) {
            prevButton.addEventListener('click', () => {
                currentIndex = (currentIndex - 1 + sliderItems.length) % sliderItems.length;
                updateSlider();
            });

            nextButton.addEventListener('click', () => {
                currentIndex = (currentIndex + 1) % sliderItems.length;
                updateSlider();
            });
        }
    });
</script>

<?php
$content = ob_get_clean();
require '../interface/templates/layout.php';
?>
