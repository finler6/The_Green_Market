<?php
session_start();
require '../backend/auth.php';
require '../backend/db.php';
require '../backend/validation.php';
require '../interface/templates/navigation.php';

// Проверяем роль
ensureRole('moderator');

// Обработка предложений
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['proposal_id'])) {
    $proposal_id = validateInt($_POST['proposal_id'], 1);
    $action = $_POST['action'];

    if ($proposal_id && in_array($action, ['approve', 'reject'])) {
        if ($action === 'approve') {
            try {
                // Начинаем транзакцию
                $pdo->beginTransaction();

                // Добавляем категорию в таблицу Categories
                $query = "INSERT INTO categories (name, parent_id)
                          SELECT name, parent_id FROM categoryproposals WHERE id = :proposal_id";
                $stmt = $pdo->prepare($query);
                $stmt->execute(['proposal_id' => $proposal_id]);

                // Обновляем статус предложения
                $query = "UPDATE categoryproposals SET status = 'approved' WHERE id = :proposal_id";
                $stmt = $pdo->prepare($query);
                $stmt->execute(['proposal_id' => $proposal_id]);

                // Завершаем транзакцию
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Failed to approve category: " . $e->getMessage();
            }
        } elseif ($action === 'reject') {
            $query = "UPDATE categoryproposals SET status = 'rejected' WHERE id = :proposal_id";
            $stmt = $pdo->prepare($query);
            $stmt->execute(['proposal_id' => $proposal_id]);
        }
    }
}

// Получение предложений только после обработки
$query = "SELECT categoryproposals.id, categoryproposals.name, categoryproposals.status, users.name AS user_name 
          FROM categoryproposals
          JOIN users ON categoryproposals.user_id = Users.id
          WHERE categoryproposals.status = 'pending'";
$proposals = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderate Categories</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-5">
<?php renderNavigation('moderator'); ?>
<h1>Moderate Category Proposals</h1>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<table class="table table-striped">
    <thead>
    <tr>
        <th>Name</th>
        <th>Proposed By</th>
        <th>Action</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($proposals as $proposal): ?>
        <tr>
            <td><?= htmlspecialchars($proposal['name']) ?></td>
            <td><?= htmlspecialchars($proposal['user_name']) ?></td>
            <td>
                <form method="POST" action="moderate_categories.php" class="d-inline">
                    <input type="hidden" name="proposal_id" value="<?= htmlspecialchars($proposal['id']) ?>">
                    <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">Approve</button>
                    <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm">Reject</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php if (empty($proposals)): ?>
    <p class="text-muted">No pending proposals.</p>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
