<?php
session_start();
require '../backend/db.php';
require '../interface/templates/navigation.php';
require '../backend/auth.php';

ensureRole('farmer');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Добавление нового события
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_event'])) {
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token.');
    }
    $name = htmlspecialchars(trim($_POST['name']));
    $location = htmlspecialchars(trim($_POST['location']));
    $date = $_POST['date'];

    if (!empty($name) && !empty($location) && !empty($date)) {
        $query = "INSERT INTO Events (name, location, date, organizer_id) VALUES (:name, :location, :date, :organizer_id)";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['name' => $name, 'location' => $location, 'date' => $date, 'organizer_id' => $_SESSION['user_id']]);
        $success = "Event created successfully!";
    } else {
        $error = "Please fill in all fields.";
    }
}

// Получение списка событий фермера
$query = "SELECT id, name, location, date FROM Events WHERE organizer_id = :organizer_id";
$stmt = $pdo->prepare($query);
$stmt->execute(['organizer_id' => $_SESSION['user_id']]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-5">
<?php renderNavigation($_SESSION['user_role']); ?>
<h1>Manage Events</h1>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Список событий -->
<h2>Your Events</h2>
<table class="table table-striped">
    <thead>
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Location</th>
        <th>Date</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($events as $event): ?>
        <tr>
            <td><?= htmlspecialchars($event['id']) ?></td>
            <td><?= htmlspecialchars($event['name']) ?></td>
            <td><?= htmlspecialchars($event['location']) ?></td>
            <td><?= htmlspecialchars($event['date']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<!-- Добавление нового события -->
<h2>Add New Event</h2>
<form method="POST" action="manage_events.php">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <div class="mb-3">
        <label for="name" class="form-label">Event Name</label>
        <input type="text" id="name" name="name" class="form-control" required>
    </div>
    <div class="mb-3">
        <label for="location" class="form-label">Location</label>
        <input type="text" id="location" name="location" class="form-control" required>
    </div>
    <div class="mb-3">
        <label for="date" class="form-label">Date</label>
        <input type="date" id="date" name="date" class="form-control" required>
    </div>
    <button type="submit" name="add_event" class="btn btn-success">Create Event</button>
</form>
</body>
</html>
