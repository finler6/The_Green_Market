<?php
session_start();
require '../backend/db.php';
require '../backend/auth.php';

ensureRole('moderator');

$title = 'Manage Applications';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_farmer_application'])) {
        $application_id = (int)$_POST['application_id'];
        $reviewer_id = $_SESSION['user_id'];

        $stmt = $pdo->prepare("UPDATE farmerapplications SET status = 'approved', reviewer_id = :reviewer_id, reviewed_date = NOW() WHERE id = :id");
        $stmt->execute(['reviewer_id' => $reviewer_id, 'id' => $application_id]);

        $stmt = $pdo->prepare("SELECT user_id FROM farmerapplications WHERE id = :id");
        $stmt->execute(['id' => $application_id]);
        $user_id = $stmt->fetchColumn();

        $stmt = $pdo->prepare("UPDATE users SET role = 'farmer' WHERE id = :user_id");
        $stmt->execute(['user_id' => $user_id]);

        $success = 'Farmer application approved successfully.';
    } elseif (isset($_POST['reject_farmer_application'])) {
        $application_id = (int)$_POST['application_id'];
        $reviewer_id = $_SESSION['user_id'];

        $stmt = $pdo->prepare("UPDATE farmerapplications SET status = 'rejected', reviewer_id = :reviewer_id, reviewed_date = NOW() WHERE id = :id");
        $stmt->execute(['reviewer_id' => $reviewer_id, 'id' => $application_id]);

        $success = 'Farmer application rejected.';
    } elseif (isset($_POST['approve_event_application'])) {
        $application_id = (int)$_POST['application_id'];
        $reviewer_id = $_SESSION['user_id'];

        $stmt = $pdo->prepare("SELECT * FROM eventapplications WHERE id = :id");
        $stmt->execute(['id' => $application_id]);
        $event_application = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($event_application) {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO events (name, location, date, organizer_id, description) VALUES (:name, :location, :date, :organizer_id, :description)");
                $stmt->execute([
                    'name' => $event_application['name'],
                    'location' => $event_application['location'],
                    'date' => $event_application['date'],
                    'organizer_id' => $event_application['organizer_id'],
                    'description' => $event_application['description']
                ]);

                $stmt = $pdo->prepare("UPDATE eventapplications SET status = 'approved', reviewer_id = :reviewer_id, reviewed_date = NOW() WHERE id = :id");
                $stmt->execute(['reviewer_id' => $reviewer_id, 'id' => $application_id]);

                $pdo->commit();

                $success = 'Event application approved and event created successfully.';
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Failed to approve event application: ' . $e->getMessage();
            }
        } else {
            $error = 'Event application not found.';
        }
    } elseif (isset($_POST['reject_event_application'])) {
        $application_id = (int)$_POST['application_id'];
        $reviewer_id = $_SESSION['user_id'];

        $stmt = $pdo->prepare("UPDATE eventapplications SET status = 'rejected', reviewer_id = :reviewer_id, reviewed_date = NOW() WHERE id = :id");
        $stmt->execute(['reviewer_id' => $reviewer_id, 'id' => $application_id]);

        $success = 'Event application rejected.';
    }
}

$stmt = $pdo->prepare("SELECT fa.*, u.name AS user_name FROM farmerapplications fa JOIN users u ON fa.user_id = u.id WHERE fa.status = 'pending' ORDER BY fa.application_date ASC");
$stmt->execute();
$farmer_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT ea.*, u.name AS organizer_name FROM eventapplications ea JOIN users u ON ea.organizer_id = u.id WHERE ea.status = 'pending' ORDER BY ea.application_date ASC");
$stmt->execute();
$event_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<h1 class="mb-4">Manage Applications</h1>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<h2>Farmer Applications</h2>
<?php if (empty($farmer_applications)): ?>
    <p>No farmer applications to review.</p>
<?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Applicant</th>
                <th>Application Text</th>
                <th>Application Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($farmer_applications as $application): ?>
            <tr>
                <td><?= htmlspecialchars($application['user_name']) ?></td>
                <td><?= nl2br(htmlspecialchars($application['application_text'])) ?></td>
                <td><?= htmlspecialchars($application['application_date']) ?></td>
                <td>
                    <form method="POST" action="manage_applications.php" class="d-inline">
                        <input type="hidden" name="application_id" value="<?= $application['id'] ?>">
                        <button type="submit" name="approve_farmer_application" class="btn btn-success btn-sm">Approve</button>
                    </form>
                    <form method="POST" action="manage_applications.php" class="d-inline">
                        <input type="hidden" name="application_id" value="<?= $application['id'] ?>">
                        <button type="submit" name="reject_farmer_application" class="btn btn-danger btn-sm">Reject</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<h2>Event Applications</h2>
<?php if (empty($event_applications)): ?>
    <p>No event applications to review.</p>
<?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Event Name</th>
                <th>Organizer</th>
                <th>Location</th>
                <th>Date</th>
                <th>Description</th>
                <th>Application Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($event_applications as $application): ?>
            <tr>
                <td><?= htmlspecialchars($application['name']) ?></td>
                <td><?= htmlspecialchars($application['organizer_name']) ?></td>
                <td><?= htmlspecialchars($application['location']) ?></td>
                <td><?= htmlspecialchars($application['date']) ?></td>
                <td><?= nl2br(htmlspecialchars($application['description'])) ?></td>
                <td><?= htmlspecialchars($application['application_date']) ?></td>
                <td>
                    <form method="POST" action="manage_applications.php" class="d-inline">
                        <input type="hidden" name="application_id" value="<?= $application['id'] ?>">
                        <button type="submit" name="approve_event_application" class="btn btn-success btn-sm">Approve</button>
                    </form>
                    <form method="POST" action="manage_applications.php" class="d-inline">
                        <input type="hidden" name="application_id" value="<?= $application['id'] ?>">
                        <button type="submit" name="reject_event_application" class="btn btn-danger btn-sm">Reject</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php
$content = ob_get_clean();
require '../interface/templates/layout.php';
?>
