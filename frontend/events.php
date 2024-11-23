<?php
session_start();
require '../backend/db.php';
require '../backend/auth.php';

$title = 'Events';
$logged_in = isset($_SESSION['user_id']); // Проверяем, авторизован ли пользователь
$user_role = $_SESSION['user_role'] ?? '';
$is_admin_or_moderator = in_array($user_role, ['admin', 'moderator']);
$is_farmer_or_higher = $is_admin_or_moderator || $user_role === 'farmer';

$interested_events = [];

// Проверяем, авторизован ли пользователь
if ($logged_in) {
    $is_interested_query = "SELECT event_id FROM UserInterests WHERE user_id = :user_id";
    $is_interested_stmt = $pdo->prepare($is_interested_query);
    $is_interested_stmt->execute(['user_id' => $_SESSION['user_id']]);
    $interested_events = $is_interested_stmt->fetchAll(PDO::FETCH_COLUMN);
}


// Генерация CSRF токена
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Добавление нового события
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_event']) && $is_farmer_or_higher) {
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token.');
    }
    $name = htmlspecialchars(trim($_POST['name']));
    $location = htmlspecialchars(trim($_POST['location']));
    $date = $_POST['date'];
    $organizer_id = $_SESSION['user_id'];

    if (!empty($name) && !empty($location) && !empty($date)) {
        $query = "INSERT INTO Events (name, location, date, organizer_id) VALUES (:name, :location, :date, :organizer_id)";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['name' => $name, 'location' => $location, 'date' => $date, 'organizer_id' => $organizer_id]);
        $success = "Event created successfully!";
    } else {
        $error = "Please fill in all fields.";
    }
}

// Удаление события
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event']) && $is_farmer_or_higher) {
    $event_id = (int)$_POST['event_id'];

    // Удаление только для своих событий или для админов/модераторов
    $query = $is_admin_or_moderator
        ? "DELETE FROM Events WHERE id = :event_id"
        : "DELETE FROM Events WHERE id = :event_id AND organizer_id = :organizer_id";
    $stmt = $pdo->prepare($query);
    $params = ['event_id' => $event_id];
    if (!$is_admin_or_moderator) {
        $params['organizer_id'] = $_SESSION['user_id'];
    }
    $stmt->execute($params);
    if ($stmt->rowCount() > 0) {
        $success = "Event deleted successfully!";
    } else {
        $error = "You can only delete your own events.";
    }
}

// Добавление события в интересы
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_interests']) && $logged_in) {
    $event_id = (int)$_POST['event_id'];

    $query = "INSERT IGNORE INTO UserInterests (user_id, event_id) VALUES (:user_id, :event_id)";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['user_id' => $_SESSION['user_id'], 'event_id' => $event_id]);
}

// Удаление события из интересов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_from_interests']) && $logged_in) {
    $event_id = (int)$_POST['event_id'];

    $query = "DELETE FROM UserInterests WHERE user_id = :user_id AND event_id = :event_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['user_id' => $_SESSION['user_id'], 'event_id' => $event_id]);
}

// Обновление события
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_event']) && $is_farmer_or_higher) {
    $event_id = (int)$_POST['event_id'];
    $name = htmlspecialchars(trim($_POST['name']));
    $location = htmlspecialchars(trim($_POST['location']));
    $date = $_POST['date'];

    // Изменение только своих событий или любых событий для админов/модераторов
    $query = $is_admin_or_moderator
        ? "UPDATE Events SET name = :name, location = :location, date = :date WHERE id = :event_id"
        : "UPDATE Events SET name = :name, location = :location, date = :date WHERE id = :event_id AND organizer_id = :organizer_id";
    $stmt = $pdo->prepare($query);
    $params = ['event_id' => $event_id, 'name' => $name, 'location' => $location, 'date' => $date];
    if (!$is_admin_or_moderator) {
        $params['organizer_id'] = $_SESSION['user_id'];
    }
    $stmt->execute($params);
    if ($stmt->rowCount() > 0) {
        $success = "Event updated successfully!";
    } else {
        $error = "You can only edit your own events.";
    }
}

// Фильтрация событий
$where_clauses = [];
$params = [];
if (!empty($_GET['name'])) {
    $where_clauses[] = "Events.name LIKE :name";
    $params['name'] = "%" . htmlspecialchars($_GET['name']) . "%";
}
if (!empty($_GET['location'])) {
    $where_clauses[] = "Events.location LIKE :location";
    $params['location'] = "%" . htmlspecialchars($_GET['location']) . "%";
}
if (!empty($_GET['date'])) {
    $where_clauses[] = "Events.date = :date";
    $params['date'] = htmlspecialchars($_GET['date']);
}
$where_clause = $where_clauses ? "WHERE " . implode(" AND ", $where_clauses) : "";

$filter = $_GET['filter'] ?? null;

if ($filter === 'my_events') {
    // Фильтрация только для событий текущего пользователя
    $where_clauses[] = "Events.organizer_id = :user_id";
    $params['user_id'] = $_SESSION['user_id'];
}

if ($filter === 'my_interests') {
    // Фильтрация для интересов текущего пользователя
    $query = "SELECT Events.id, Events.name, Events.location, Events.date, Events.organizer_id, Users.name AS organizer 
              FROM UserInterests
              JOIN Events ON UserInterests.event_id = Events.id
              LEFT JOIN Users ON Events.organizer_id = Users.id
              WHERE UserInterests.user_id = :user_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Обычный запрос всех событий с фильтрацией
    $where_clause = $where_clauses ? "WHERE " . implode(" AND ", $where_clauses) : "";
    $query = "SELECT Events.id, Events.name, Events.location, Events.date, Events.organizer_id, Users.name AS organizer 
              FROM Events
              LEFT JOIN Users ON Events.organizer_id = Users.id
              $where_clause";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// Определение статуса события
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

ob_start();
?>
<?php if ($is_farmer_or_higher): ?>
    <!-- Кнопка для добавления нового события -->
    <button type="button" class="btn btn-primary mt-4" data-bs-toggle="modal" data-bs-target="#addEventModal">
        Create New Event
    </button>
<?php endif; ?>
    <h1 class="text-center mb-4">Events</h1>
<?php if ($logged_in): ?>
    <div class="mb-4 d-flex justify-content-between">
        <?php if ($is_farmer_or_higher): ?>
            <form method="GET" action="events.php" class="d-inline">
                <button type="submit" name="filter" value="my_events" class="btn btn-outline-primary">My Events</button>
            </form>
        <?php endif; ?>
        <form method="GET" action="events.php" class="d-inline">
            <button type="submit" name="filter" value="my_interests" class="btn btn-outline-secondary">My Interests</button>
        </form>
    </div>
<?php endif; ?>


    <!-- Форма фильтрации -->
    <form method="GET" action="events.php" class="mb-4">
        <div class="row g-3 align-items-center">
            <!-- Поле для ввода названия события -->
            <div class="col-md-3">
                <label for="name" class="form-label fw-bold">Event Name</label>
                <input type="text" id="name" name="name" class="form-control"
                       placeholder="Enter event name"
                       value="<?= htmlspecialchars($_GET['name'] ?? '') ?>">
            </div>
            <!-- Поле для ввода местоположения события -->
            <div class="col-md-3">
                <label for="location" class="form-label fw-bold">Location</label>
                <input type="text" id="location" name="location" class="form-control"
                       placeholder="Enter location"
                       value="<?= htmlspecialchars($_GET['location'] ?? '') ?>">
            </div>
            <!-- Поле для выбора даты события -->
            <div class="col-md-3">
                <label for="date" class="form-label fw-bold">Date</label>
                <input type="date" id="date" name="date" class="form-control"
                       value="<?= htmlspecialchars($_GET['date'] ?? '') ?>">
            </div>
            <!-- Кнопки управления -->
            <div class="col-md-3 d-flex align-items-center justify-content-end">
                <button type="submit" class="btn btn-primary me-2 btn-filter">Filter</button>
                <a href="events.php" class="btn btn-secondary btn-filter">Reset</a>
            </div>
        </div>
    </form>

    <!-- Список событий -->
    <div class="events-container">
        <?php foreach ($events as $event):
            $is_interested = $logged_in && in_array($event['id'], $interested_events);?>
            <div class="event-card">
                <a href="event.php?id=<?= htmlspecialchars($event['id']) ?>" class="event-card-link"></a>
                <h3><?= htmlspecialchars($event['name']) ?></h3>
                <p><strong>Location:</strong> <?= htmlspecialchars($event['location']) ?></p>
                <p><strong>Date:</strong> <?= htmlspecialchars(date('F j, Y', strtotime($event['date']))) ?></p>
                <p><strong>Organizer:</strong> <?= htmlspecialchars($event['organizer']) ?></p>
                <p><strong>Status:</strong>
                    <span class="<?= getEventStatus($event['date']) === 'Completed' ? 'text-danger' : (getEventStatus($event['date']) === 'Ongoing' ? 'text-warning' : 'text-success') ?>">
                <?= getEventStatus($event['date']) ?>
                </span>
                </p>
                <div class="mt-3">
                    <?php if ($logged_in): ?>
                        <button
                                class="btn btn-<?= $is_interested ? 'danger' : 'primary' ?> btn-sm toggle-interest"
                                data-event-id="<?= $event['id'] ?>"
                                data-action="<?= $is_interested ? 'remove' : 'add' ?>">
                            <?= $is_interested ? 'Remove from Interests' : 'Add to Interests' ?>
                        </button>
                    <?php endif; ?>
                    <?php if ($is_farmer_or_higher && ($is_admin_or_moderator || $event['organizer_id'] == $_SESSION['user_id'])): ?>
                        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editEventModal"
                                data-event-id="<?= $event['id'] ?>"
                                data-event-name="<?= htmlspecialchars($event['name']) ?>"
                                data-event-location="<?= htmlspecialchars($event['location']) ?>"
                                data-event-date="<?= htmlspecialchars($event['date']) ?>">
                            Edit
                        </button>
                        <form method="POST" action="events.php" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="event_id" value="<?= htmlspecialchars($event['id']) ?>">
                            <button type="submit" name="delete_event" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Модальное окно для добавления события -->
    <div class="modal fade" id="addEventModal" tabindex="-1" aria-labelledby="addEventModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="events.php">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addEventModalLabel">Propose New Event</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <div class="mb-3">
                            <label for="eventName" class="form-label">Event Name</label>
                            <input type="text" id="eventName" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="eventLocation" class="form-label">Location</label>
                            <input type="text" id="eventLocation" name="location" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="eventDate" class="form-label">Date</label>
                            <input type="date" id="eventDate" name="date" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="add_event" class="btn btn-success">Add Event</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Модальное окно для редактирования события -->
    <div class="modal fade" id="editEventModal" tabindex="-1" aria-labelledby="editEventModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="events.php">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editEventModalLabel">Edit Event</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="event_id" id="editEventId" value="">
                        <div class="mb-3">
                            <label for="editEventName" class="form-label">Event Name</label>
                            <input type="text" id="editEventName" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="editEventLocation" class="form-label">Location</label>
                            <input type="text" id="editEventLocation" name="location" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="editEventDate" class="form-label">Date</label>
                            <input type="date" id="editEventDate" name="date" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="edit_event" class="btn btn-success">Update Event</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const editEventModal = document.getElementById('editEventModal');
            editEventModal.addEventListener('show.bs.modal', (event) => {
                const button = event.relatedTarget;
                const eventId = button.getAttribute('data-event-id');
                const eventName = button.getAttribute('data-event-name');
                const eventLocation = button.getAttribute('data-event-location');
                const eventDate = button.getAttribute('data-event-date');

                document.getElementById('editEventId').value = eventId;
                document.getElementById('editEventName').value = eventName;
                document.getElementById('editEventLocation').value = eventLocation;
                document.getElementById('editEventDate').value = eventDate;
            });
        });
    </script>
<?php
$content = ob_get_clean();
require '../interface/templates/layout.php';
