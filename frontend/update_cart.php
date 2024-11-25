<?php
session_start();
require '../backend/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : null;
    $action = $_POST['action'] ?? null;
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : null;

    if ($product_id && isset($_SESSION['cart'][$product_id])) {
        switch ($action) {
            case 'increase':
                $query = "SELECT quantity FROM products WHERE id = :product_id";
                $stmt = $pdo->prepare($query);
                $stmt->execute(['product_id' => $product_id]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($product && $_SESSION['cart'][$product_id]['quantity'] < $product['quantity']) {
                    $_SESSION['cart'][$product_id]['quantity']++;
                }
                break;

            case 'decrease':
                if ($_SESSION['cart'][$product_id]['quantity'] > 1) {
                    $_SESSION['cart'][$product_id]['quantity']--;
                }
                break;

            case 'remove':
                unset($_SESSION['cart'][$product_id]);
                break;

            default:
                if ($quantity !== null) {
                    $query = "SELECT quantity FROM products WHERE id = :product_id";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute(['product_id' => $product_id]);
                    $product = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($product) {
                        $available_quantity = $product['quantity'];
                        if ($quantity > 0 && $quantity <= $available_quantity) {
                            $_SESSION['cart'][$product_id]['quantity'] = $quantity;
                        } elseif ($quantity > $available_quantity) {
                            $_SESSION['cart'][$product_id]['quantity'] = $available_quantity;
                        } else {
                            unset($_SESSION['cart'][$product_id]);
                        }
                    }
                }
                break;
        }
    }

    header('Location: cart.php');
    exit;
}
?>
