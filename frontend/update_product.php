<?php
session_start();
require '../backend/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'farmer') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

$product_id = $data['product_id'] ?? null;
$price = $data['price'] ?? null;
$price_unit = $data['price_unit'] ?? null;
$quantity = $data['quantity'] ?? null;
$quantity_unit = $data['quantity_unit'] ?? null;
$csrf_token = $data['csrf_token'] ?? '';

if ($csrf_token !== ($_SESSION['csrf_token'] ?? '')) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid CSRF token.']);
    exit;
}

$check_query = "SELECT farmer_id FROM products WHERE id = :product_id";
$check_stmt = $pdo->prepare($check_query);
$check_stmt->execute(['product_id' => $product_id]);
$product = $check_stmt->fetch(PDO::FETCH_ASSOC);

if (!$product || $product['farmer_id'] != $current_user_id) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access to product.']);
    exit;
}

$update_fields = [];
$params = ['product_id' => $product_id];

if ($price !== null) {
    $update_fields[] = "price = :price";
    $params['price'] = $price;
}
if ($price_unit !== null) {
    $update_fields[] = "price_unit = :price_unit";
    $params['price_unit'] = $price_unit;
}
if ($quantity !== null) {
    $update_fields[] = "quantity = :quantity";
    $params['quantity'] = $quantity;
}
if ($quantity_unit !== null) {
    $update_fields[] = "quantity_unit = :quantity_unit";
    $params['quantity_unit'] = $quantity_unit;
}

if (!empty($update_fields)) {
    $update_query = "UPDATE products SET " . implode(", ", $update_fields) . " WHERE id = :product_id";
    $update_stmt = $pdo->prepare($update_query);
    $update_stmt->execute($params);
}

http_response_code(200);
echo json_encode(['success' => 'Product updated successfully.']);
