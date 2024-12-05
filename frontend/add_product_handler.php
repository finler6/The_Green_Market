<?php
session_start();
require '../backend/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'farmer') {
    header('Location: ../frontend/login.php');
    exit;
}

$current_user_id = $_SESSION['user_id'];
$csrf_token = $_POST['csrf_token'] ?? '';

if ($csrf_token !== ($_SESSION['csrf_token'] ?? '')) {
    die('Invalid CSRF token.');
}

$name = htmlspecialchars(trim($_POST['name']));
$price = (float)$_POST['price'];
$quantity = (int)$_POST['quantity'];
$description = htmlspecialchars(trim($_POST['description']));
$category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : null;

if (empty($name) || $price <= 0 || $quantity < 0 || empty($description) || empty($category_id)) {
    $_SESSION['error'] = 'Invalid product data.';
    header('Location: ../frontend/profile.php');
    exit;
}

$category_query = "SELECT id FROM categories WHERE id = :category_id";
$category_stmt = $pdo->prepare($category_query);
$category_stmt->execute(['category_id' => $category_id]);
if ($category_stmt->rowCount() === 0) {
    $_SESSION['error'] = 'Invalid category selected.';
    header('Location: ../frontend/profile.php');
    exit;
}

try {
    $query = "INSERT INTO Products (name, price, quantity, description, category_id, farmer_id) 
              VALUES (:name, :price, :quantity, :description, :category_id, :farmer_id)";
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'name' => $name,
        'price' => $price,
        'quantity' => $quantity,
        'description' => $description,
        'category_id' => $category_id,
        'farmer_id' => $current_user_id
    ]);

    $_SESSION['success'] = 'Product added successfully.';
    header('Location: ../frontend/profile.php');
    exit;
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error adding product: ' . $e->getMessage();
    header('Location: ../frontend/profile.php');
    exit;
}
