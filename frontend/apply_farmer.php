<?php
session_start();
require '../backend/db.php';
require '../backend/auth.php';

ensureLoggedIn();

$title = 'Apply to Become a Farmer';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $application_text = htmlspecialchars(trim($_POST['application_text']));

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM farmerapplications WHERE user_id = :user_id AND status = 'pending'");
    $stmt->execute(['user_id' => $user_id]);
    $existing_application = $stmt->fetchColumn();

    if ($existing_application) {
        $error = 'You have already submitted an application.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO farmerapplications (user_id, application_text) VALUES (:user_id, :application_text)");
        $stmt->execute(['user_id' => $user_id, 'application_text' => $application_text]);

        $success = 'Your application has been submitted.';
    }
}

ob_start();
?>

<h1 class="mb-4">Apply to Become a Farmer</h1>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" action="apply_farmer.php">
    <div class="mb-3">
        <label for="application_text" class="form-label">Why do you want to become a farmer?</label>
        <textarea class="form-control" id="application_text" name="application_text" rows="5" required></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Submit Application</button>
</form>

<?php
$content = ob_get_clean();
require '../interface/templates/layout.php';
?>
