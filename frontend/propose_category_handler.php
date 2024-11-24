<?php
session_start();
require '../backend/auth.php';
require '../backend/db.php';
require '../backend/validation.php';

// Проверяем авторизацию
ensureLoggedIn();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['propose_category'])) {
    $name = validateString($_POST['name']);
    $parent_id = validateInt($_POST['parent_id'], 1) ?: null;

    if ($name) {
        $query = "INSERT INTO categoryproposals (name, parent_id, user_id) VALUES (:name, :parent_id, :user_id)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            'name' => $name,
            'parent_id' => $parent_id,
            'user_id' => $_SESSION['user_id']
        ]);
        $_SESSION['success'] = "Your proposal has been submitted for review.";
    } else {
        $_SESSION['error'] = "Invalid category name.";
    }
    header('Location: customer_dashboard.php');
    exit;
}
?>