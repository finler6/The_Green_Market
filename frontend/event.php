<?php
session_start();
umask(0022);
require '../backend/db.php';

$event_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$event_id) {
    header('Location: events.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$query = "
    SELECT events.id, events.name, events.location, events.date, events.description, 
           events.organizer_id, users.name AS organizer 
    FROM events
    LEFT JOIN users ON events.organizer_id = users.id
    WHERE events.id = :event_id
";
$stmt = $pdo->prepare($query);
$stmt->execute(['event_id' => $event_id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    $error = "Событие не найдено.";
}

$image_query = "SELECT id, image_path FROM eventimages WHERE event_id = :event_id";
$image_stmt = $pdo->prepare($image_query);
$image_stmt->execute(['event_id' => $event_id]);
$event_images = $image_stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($event_images)) {
    $event_images = [['id' => 0, 'image_path' => '../images/placeholder.png']];
}

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

$logged_in = isset($_SESSION['user_id']);
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? null;
$is_admin_or_moderator = in_array($user_role, ['admin', 'moderator']);
$is_event_creator = $event['organizer_id'] == $user_id;
$can_edit_event = $is_admin_or_moderator || $is_event_creator;

$is_interested = false;

if ($logged_in) {
    $query = "SELECT id FROM userinterests WHERE user_id = :user_id AND event_id = :event_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['user_id' => $user_id, 'event_id' => $event_id]);
    $is_interested = $stmt->rowCount() > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $logged_in) {
    $contentType = $_SERVER["CONTENT_TYPE"] ?? '';

    if (strpos($contentType, 'application/json') !== false) {
        // Handle JSON request
        $data = json_decode(file_get_contents('php://input'), true);
        $csrf_token = $data['csrf_token'] ?? '';
        $action = $data['action'] ?? '';
    } elseif (strpos($contentType, 'multipart/form-data') !== false) {
        // Handle form data
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

    if ($action === 'add' || $action === 'remove') {
        $event_id = (int)($data['event_id'] ?? 0);

        if ($action === 'add') {
            $add_interest_query = "INSERT IGNORE INTO userinterests (user_id, event_id) VALUES (:user_id, :event_id)";
            $add_interest_stmt = $pdo->prepare($add_interest_query);
            $add_interest_stmt->execute(['user_id' => $user_id, 'event_id' => $event_id]);

            echo json_encode(['success' => true]);
            exit;
        } elseif ($action === 'remove') {
            $remove_interest_query = "DELETE FROM userinterests WHERE user_id = :user_id AND event_id = :event_id";
            $remove_interest_stmt = $pdo->prepare($remove_interest_query);
            $remove_interest_stmt->execute(['user_id' => $user_id, 'event_id' => $event_id]);

            echo json_encode(['success' => true]);
            exit;
        }
    } elseif ($action === 'delete_event' && $can_edit_event) {
        $pdo->beginTransaction();
        try {
            $image_query = "SELECT image_path FROM eventimages WHERE event_id = :event_id";
            $image_stmt = $pdo->prepare($image_query);
            $image_stmt->execute(['event_id' => $event_id]);
            $images = $image_stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($images as $image) {
                $filePath = '..' . $image['image_path'];
                if (file_exists($filePath) && is_writable($filePath)) {
                    unlink($filePath);
                }
            }

            $delete_images_query = "DELETE FROM eventimages WHERE event_id = :event_id";
            $delete_images_stmt = $pdo->prepare($delete_images_query);
            $delete_images_stmt->execute(['event_id' => $event_id]);

            $delete_interests_query = "DELETE FROM userinterests WHERE event_id = :event_id";
            $delete_interests_stmt = $pdo->prepare($delete_interests_query);
            $delete_interests_stmt->execute(['event_id' => $event_id]);

            $delete_event_query = "DELETE FROM events WHERE id = :event_id";
            $delete_event_stmt = $pdo->prepare($delete_event_query);
            $delete_event_stmt->execute(['event_id' => $event_id]);

            $pdo->commit();

            echo json_encode(['success' => true]);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Failed to delete the event.']);
            exit;
        }
    } elseif ($action === 'update_event' && $can_edit_event) {
        $name = trim($_POST['name'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $date = trim($_POST['date'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($name) || empty($location) || empty($date)) {
            echo json_encode(['success' => false, 'error' => 'All fields are required.']);
            exit;
        }

        $update_query = "UPDATE events SET name = :name, location = :location, date = :date, description = :description WHERE id = :event_id";
        $update_stmt = $pdo->prepare($update_query);
        $update_stmt->execute([
            'name' => $name,
            'location' => $location,
            'date' => $date,
            'description' => $description,
            'event_id' => $event_id
        ]);

        if (!empty($_FILES['images']['name'][0])) {
            $uploadDir = '../images/';
            $uploadDirURL = '../images/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
                chmod($uploadDir, 0755);
            }

            foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
                $fileName = basename($_FILES['images']['name'][$key]);
                $uniqueFileName = uniqid() . '_' . $fileName;
                $targetFilePath = $uploadDir . $uniqueFileName;
                $imageURL = $uploadDirURL . $uniqueFileName;

                if (move_uploaded_file($tmpName, $targetFilePath)) {
                    chmod($targetFilePath, 0644);

                    $insertImageQuery = "INSERT INTO eventimages (event_id, image_path) VALUES (:event_id, :image_path)";
                    $insertImageStmt = $pdo->prepare($insertImageQuery);
                    $insertImageStmt->execute([
                        'event_id' => $event_id,
                        'image_path' => $imageURL
                    ]);
                }

            }
        }

        echo json_encode(['success' => true, 'message' => 'Event updated.']);
        exit;
    } elseif ($action === 'delete_image' && $can_edit_event) {
        $image_id = (int)($data['image_id'] ?? 0);

        $image_query = "SELECT image_path FROM eventimages WHERE id = :image_id AND event_id = :event_id";
        $image_stmt = $pdo->prepare($image_query);
        $image_stmt->execute(['image_id' => $image_id, 'event_id' => $event_id]);
        $image = $image_stmt->fetch(PDO::FETCH_ASSOC);

        if ($image) {
            $filePath = $image['image_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $delete_query = "DELETE FROM eventimages WHERE id = :image_id";
            $delete_stmt = $pdo->prepare($delete_query);
            $delete_stmt->execute(['image_id' => $image_id]);

            echo json_encode(['success' => true, 'message' => 'Image deleted.']);
            exit;
        } else {
            echo json_encode(['success' => false, 'error' => 'Image not found.']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action.']);
        exit;
    }
}

ob_start();
?>

<h1 class="text-center mb-4"><?= htmlspecialchars($event['name'] ?? 'Event Details') ?></h1>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php else: ?>
    <div class="hero-slider">
        <div class="slider-container">
            <?php foreach ($event_images as $image): ?>

                <div class="slider-item">
                    <img src="<?= htmlspecialchars($image['image_path']) ?>" alt="Event Image">
                    <?php if ($can_edit_event && $image['id'] != 0): ?>
                        <button class="btn btn-danger btn-sm delete-image-btn" data-image-id="<?= $image['id'] ?>">Delete</button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (count($event_images) > 1): ?>
            <button class="slider-prev">&#10094;</button>
            <button class="slider-next">&#10095;</button>
        <?php endif; ?>
    </div>

    <div class="event-details">
        <p><strong>Location :</strong> <?= htmlspecialchars($event['location']) ?></p>
        <p><strong>Date :</strong> <?= htmlspecialchars(date('F j, Y', strtotime($event['date']))) ?></p>
        <p><strong>Organizer :</strong> <a href="profile.php?id=<?= $event['organizer_id'] ?>" style="color: inherit; text-decoration: none;"><?= htmlspecialchars($event['organizer']) ?></a></p>
        <p><strong>Status :</strong> 
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
            <p class="text-muted">No description yet.</p>
        <?php endif; ?>

        <?php if ($logged_in): ?>
            <div class="d-flex mt-3">
                <button
                    class="btn btn-<?= $is_interested ? 'danger' : 'primary' ?> me-2 toggle-interest"
                    data-event-id="<?= $event_id ?>"
                    data-action="<?= $is_interested ? 'remove' : 'add' ?>">
                    <?= $is_interested ? 'Remove from interests' : 'Add to interests' ?>
                </button>

                <?php if ($can_edit_event): ?>
                    <button class="btn btn-warning me-2" data-bs-toggle="modal" data-bs-target="#editEventModal">Edit</button>
                    <button class="btn btn-danger" id="deleteEventButton">Delete</button>
                <?php endif; ?>
            </div>
        <?php endif; ?>



    </div>
<?php endif; ?>

<?php if ($can_edit_event): ?>
<div class="modal fade" id="editEventModal" tabindex="-1" aria-labelledby="editEventModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form id="editEventForm">
        <div class="modal-header">
          <h5 class="modal-title" id="editEventModalLabel">Edit Event</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label for="eventName" class="form-label">Event Name</label>
            <input type="text" class="form-control" id="eventName" name="name" value="<?= htmlspecialchars($event['name']) ?>" required>
          </div>
          <div class="mb-3">
            <label for="eventLocation" class="form-label">Location</label>
            <input type="text" class="form-control" id="eventLocation" name="location" value="<?= htmlspecialchars($event['location']) ?>" required>
          </div>
          <div class="mb-3">
            <label for="eventDate" class="form-label">Date</label>
            <input type="date" class="form-control" id="eventDate" name="date" value="<?= htmlspecialchars($event['date']) ?>" required>
          </div>
          <div class="mb-3">
            <label for="eventDescription" class="form-label">Description</label>
            <textarea class="form-control" id="eventDescription" name="description" rows="4"><?= htmlspecialchars($event['description']) ?></textarea>
          </div>

          <div class="mb-3">
            <label class="form-label">Attached Images</label>
            <div id="existingImages" class="d-flex flex-wrap">
                <?php foreach ($event_images as $image): ?>
                    <?php if ($image['id'] != 0): ?>
                        <div class="position-relative m-2">
                            <img src="<?= htmlspecialchars($image['image_path']) ?>" alt="Event Image" class="img-thumbnail" style="max-width: 150px;">
                            <button type="button" class="btn btn-danger btn-sm delete-image-btn position-absolute top-0 end-0" data-image-id="<?= $image['id'] ?>">&times;</button>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Add Images</label>
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
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Changes</button>
          <input type="hidden" name="action" value="update_event">
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
            const offset = -currentIndex * 100;
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
    <?php if ($can_edit_event): ?>
    document.addEventListener('DOMContentLoaded', () => {
    const editEventForm = document.getElementById('editEventForm');
    const imageUploadArea = document.getElementById('imageUploadArea');
    const imageInput = document.getElementById('imageInput');
    const imagePreview = document.getElementById('imagePreview');
    const existingImages = document.getElementById('existingImages');

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

    editEventForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const formData = new FormData(editEventForm);

        fetch('event.php?id=<?= $event_id ?>', {
            method: 'POST',
            body: formData,
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Event successfully updated.');
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(error => console.error('Error:', error));
    });

    existingImages.addEventListener('click', (e) => {
        if (e.target.classList.contains('delete-image-btn')) {
            const imageId = e.target.dataset.imageId;
            if (confirm('Are you sure you want to delete this image?')) {
                fetch('event.php?id=<?= $event_id ?>', {
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
                        e.target.parentElement.remove();
                    } else {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        }
    });

    const deleteEventButton = document.getElementById('deleteEventButton');
    if (deleteEventButton) {
        deleteEventButton.addEventListener('click', () => {
            if (confirm('Are you sure you want to delete this event? This action cannot be undone.')) {
                fetch('event.php?id=<?= $event_id ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'delete_event',
                        csrf_token: '<?= $_SESSION['csrf_token'] ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Event deleted successfully.');
                        window.location.href = 'events.php';
                    } else {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        });
    }
});
<?php endif; ?>
</script>

<?php
$content = ob_get_clean();
require '../interface/templates/layout.php';
?>