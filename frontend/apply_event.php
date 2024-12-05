<?php
session_start();
require '../backend/db.php';
require '../backend/auth.php';

ensureLoggedIn();

$title = 'Propose an Event';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $organizer_id = $_SESSION['user_id'];
    $name = htmlspecialchars(trim($_POST['name']));
    $location = htmlspecialchars(trim($_POST['location']));
    $date = $_POST['date'];
    $description = htmlspecialchars(trim($_POST['description']));

    if ($name && $location && $date) {
        $stmt = $pdo->prepare("INSERT INTO eventapplications (name, location, date, organizer_id, description) VALUES (:name, :location, :date, :organizer_id, :description)");
        $stmt->execute([
            'name' => $name,
            'location' => $location,
            'date' => $date,
            'organizer_id' => $organizer_id,
            'description' => $description
        ]);

        $success = 'Your event proposal has been submitted.';
    } else {
        $error = 'Please fill in all required fields.';
    }
}

ob_start();
?>

<h1 class="mb-4">Propose an Event</h1>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" action="apply_event.php">
    <div class="mb-3">
        <label for="name" class="form-label">Event Name</label>
        <input type="text" class="form-control" id="name" name="name" required>
    </div>
    <div class="mb-3">
        <label for="location" class="form-label">Location</label>
        <input type="text" class="form-control" id="location" name="location" required>
    </div>
    <div class="mb-3">
        <label for="date" class="form-label">Date</label>
        <input type="date" class="form-control" id="date" name="date" required>
    </div>
    <div class="mb-3">
        <label for="description" class="form-label">Description (optional)</label>
        <textarea class="form-control" id="description" name="description" rows="5"></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Submit Proposal</button>
</form>

<?php
$content = ob_get_clean();
require '../interface/templates/layout.php';
?>
