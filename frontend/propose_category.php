<?php
session_start();
require '../backend/auth.php';
require '../backend/db.php';
require '../backend/validation.php';

ensureLoggedIn();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['propose_category'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';

    if ($csrf_token !== ($_SESSION['csrf_token'] ?? '')) {
        die('Invalid CSRF token.');
    }

    $name = validateString($_POST['category_name']);
    $parent_id = !empty($_POST['parent_category']) ? validateInt($_POST['parent_category']) : null;

    if ($name) {
        try {
            $query = "INSERT INTO categoryproposals (name, parent_id, user_id) VALUES (:name, :parent_id, :user_id)";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                'name' => $name,
                'parent_id' => $parent_id,
                'user_id' => $_SESSION['user_id']
            ]);
            $_SESSION['success'] = "Your proposal has been submitted for review.";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Failed to submit proposal: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Invalid category name.";
    }
    header('Location: profile.php');
    exit;
}
?>