<?php
session_start();
require '../backend/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] == 'customer') {
    header('Location: ../frontend/login.php');
    exit;
}

$current_user_id = $_SESSION['user_id'];
$csrf_token = $_POST['csrf_token'] ?? '';

if ($csrf_token !== ($_SESSION['csrf_token'] ?? '')) {
    die('Invalid CSRF token.');
}

$name = htmlspecialchars(trim($_POST['name']));
$price = isset($_POST['price']) ? (float)$_POST['price'] : null;
$price_unit = htmlspecialchars(trim($_POST['price_unit'] ?? ''));
$quantity = isset($_POST['quantity']) ? (float)$_POST['quantity'] : null;
$quantity_unit = htmlspecialchars(trim($_POST['quantity_unit'] ?? ''));
$description = htmlspecialchars(trim($_POST['description']));
$category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : null;

$errors = [];

if (empty($name)) {
    $errors[] = 'Product name cannot be empty.';
}

if ($price === null || $price <= 0) {
    $errors[] = 'Price must be a positive number.';
}

if (empty($price_unit)) {
    $errors[] = 'Price unit is required.';
}

if ($quantity === null || $quantity < 0) {
    $errors[] = 'Quantity must be a non-negative number.';
}

if (empty($quantity_unit)) {
    $errors[] = 'Quantity unit is required.';
}

if (empty($description)) {
    $errors[] = 'Description cannot be empty.';
}

if (empty($category_id)) {
    $errors[] = 'Category is required.';
}

$category_query = "SELECT id FROM categories WHERE id = :category_id";
$category_stmt = $pdo->prepare($category_query);
$category_stmt->execute(['category_id' => $category_id]);
if ($category_stmt->rowCount() === 0) {
    $errors[] = 'Invalid category selected.';
}

if (!empty($errors)) {
    $_SESSION['error'] = implode('<br>', $errors);
    header('Location: ../frontend/profile.php');
    exit;
}

try {
    $query = "INSERT INTO products (name, price, price_unit, quantity, quantity_unit, description, category_id, farmer_id) 
              VALUES (:name, :price, :price_unit, :quantity, :quantity_unit, :description, :category_id, :farmer_id)";
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'name' => $name,
        'price' => $price,
        'price_unit' => $price_unit,
        'quantity' => $quantity,
        'quantity_unit' => $quantity_unit,
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
