<?php
session_start();
require '../backend/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] == 'customer') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

$product_id = $data['product_id'] ?? null;
$csrf_token = $data['csrf_token'] ?? '';

if ($csrf_token !== ($_SESSION['csrf_token'] ?? '')) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid CSRF token.']);
    exit;
}

if (!$product_id || !is_numeric($product_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid product ID.']);
    exit;
}

try {
    $check_query = "SELECT farmer_id FROM products WHERE id = :product_id";
    $check_stmt = $pdo->prepare($check_query);
    $check_stmt->execute(['product_id' => $product_id]);
    $product = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product || $product['farmer_id'] != $current_user_id) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized access to product.']);
        exit;
    }

    $delete_query = "DELETE FROM products WHERE id = :product_id";
    $delete_stmt = $pdo->prepare($delete_query);
    $delete_stmt->execute(['product_id' => $product_id]);

    if ($delete_stmt->rowCount() === 0) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete product.']);
        exit;
    }

    http_response_code(200);
    echo json_encode(['success' => 'Product deleted successfully.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error.']);
    exit;
}
