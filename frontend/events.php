<?php
session_start();
require '../backend/db.php';
require '../backend/auth.php';
require '../interface/templates/navigation.php';

$logged_in = isset($_SESSION['user_id']); // Проверяем, авторизован ли пользователь

// Получение всех событий
$query = "SELECT * FROM Events";
$events = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Обработка добавления события в список интересов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_interests']) && $logged_in) {
    $event_id = (int)$_POST['event_id'];
    $user_id = $_SESSION['user_id'];

    // Проверяем, существует ли запись
    $query = "SELECT id FROM UserInterests WHERE user_id = :user_id AND event_id = :event_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['user_id' => $user_id, 'event_id' => $event_id]);
    if ($stmt->rowCount() === 0) {
        $query = "INSERT INTO UserInterests (user_id, event_id) VALUES (:user_id, :event_id)";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['user_id' => $user_id, 'event_id' => $event_id]);
        $success = "Event added to your interests!";
    } else {
        $error = "This event is already in your interests.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-5">
<?php renderNavigation($_SESSION['user_role'] ?? null); ?>
<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<h1>All Events</h1>
<table class="table table-striped">
    <thead>
    <tr>
        <th>Name</th>
        <th>Location</th>
        <th>Date</th>
        <th>Action</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($events as $event): ?>
        <tr>
            <td><?= htmlspecialchars($event['name']) ?></td>
            <td><?= htmlspecialchars($event['location']) ?></td>
            <td><?= htmlspecialchars($event['date']) ?></td>
            <td>
                <?php if ($logged_in): ?>
                    <form method="POST" action="events.php" class="d-inline">
                        <input type="hidden" name="event_id" value="<?= htmlspecialchars($event['id']) ?>">
                        <button type="submit" name="add_to_interests" class="btn btn-primary btn-sm">Add to Interests</button>
                    </form>
                <?php else: ?>
                    <span class="text-muted">Login to add to interests</span>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
