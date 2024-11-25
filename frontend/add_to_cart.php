<?php
session_start();
require '../backend/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : null;
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

    if (!$product_id || $quantity < 1) {
        header('Location: index.php');
        exit;
    }

    $query = "SELECT id, name, price, quantity AS stock FROM products WHERE id = :product_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['product_id' => $product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product || $quantity > $product['stock']) {
        $_SESSION['error'] = 'Invalid quantity or product unavailable.';
        header('Location: product.php?id=' . $product_id);
        exit;
    }

    if (!isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id] = [
            'name' => $product['name'],
            'price' => $product['price'],
            'quantity' => $quantity,
        ];
    } else {
        $_SESSION['cart'][$product_id]['quantity'] += $quantity;
        if ($_SESSION['cart'][$product_id]['quantity'] > $product['stock']) {
            $_SESSION['cart'][$product_id]['quantity'] = $product['stock'];
        }
    }

    $_SESSION['success'] = 'Product added to cart successfully.';
    header('Location: product.php?id=' . $product_id);
    exit;
}

