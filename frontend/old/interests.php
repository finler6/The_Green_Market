<?php
session_start();
require '../backend/db.php';
require '../backend/auth.php';
require '../interface/templates/navigation.php';

ensureLoggedIn(); // Убедимся, что пользователь авторизован

// Получение списка интересов
$query = "SELECT Events.name, Events.location, Events.date, UserInterests.id AS interest_id
          FROM UserInterests
          JOIN Events ON UserInterests.event_id = Events.id
          WHERE UserInterests.user_id = :user_id";
$stmt = $pdo->prepare($query);
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$interests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Удаление события из списка интересов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_interest'])) {
    $interest_id = (int)$_POST['interest_id'];
    $query = "DELETE FROM UserInterests WHERE id = :interest_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['interest_id' => $interest_id]);
    header('Location: interests.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Interests</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-5">
<?php renderNavigation($_SESSION['user_role']); ?>

<h1>My Interests</h1>
<?php if (empty($interests)): ?>
    <p class="text-muted">You have no events in your interests.</p>
<?php else: ?>
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
        <?php foreach ($interests as $interest): ?>
            <tr>
                <td><?= htmlspecialchars($interest['name']) ?></td>
                <td><?= htmlspecialchars($interest['location']) ?></td>
                <td><?= htmlspecialchars($interest['date']) ?></td>
                <td>
                    <form method="POST" action="interests.php" class="d-inline">
                        <input type="hidden" name="interest_id" value="<?= htmlspecialchars($interest['interest_id']) ?>">
                        <button type="submit" name="remove_interest" class="btn btn-danger btn-sm">Remove</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
