<?php
session_start();
require '../backend/db.php';
require 'navigation.php';

// Проверка роли клиента
if ($_SESSION['user_role'] !== 'customer') {
    header('Location: login.php');
    exit;
}

// Получение данных корзины
$cart = $_SESSION['cart'] ?? [];
$products = [];

if (!empty($cart)) {
    $placeholders = implode(',', array_fill(0, count($cart), '?'));
    $query = "SELECT id, name, price, quantity FROM Products WHERE id IN ($placeholders)";
    $stmt = $pdo->prepare($query);
    $stmt->execute(array_keys($cart));
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Обработка оформления заказа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $customer_id = $_SESSION['user_id'];
    $order_items = [];

    // Проверяем наличие товаров и создаём заказ
    foreach ($products as $product) {
        $product_id = $product['id'];
        $quantity = $cart[$product_id];

        // Проверка доступного количества
        if ($product['quantity'] < $quantity) {
            $error = "Not enough stock for product: " . htmlspecialchars($product['name']);
            break;
        }

        $total_price = $product['price'] * $quantity;
        $order_items[] = [
            'product_id' => $product_id,
            'quantity' => $quantity,
            'total_price' => $total_price,
        ];
    }

    if (empty($error)) {
        try {
            // Создаем заказ
            $pdo->beginTransaction();

            foreach ($order_items as $item) {
                $query = "INSERT INTO Orders (customer_id, product_id, quantity, total_price, status) 
                          VALUES (:customer_id, :product_id, :quantity, :total_price, 'pending')";
                $stmt = $pdo->prepare($query);
                $stmt->execute([
                    'customer_id' => $customer_id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'total_price' => $item['total_price'],
                ]);

                // Обновляем количество товара
                $query = "UPDATE Products SET quantity = quantity - :quantity WHERE id = :product_id";
                $stmt = $pdo->prepare($query);
                $stmt->execute([
                    'quantity' => $item['quantity'],
                    'product_id' => $item['product_id'],
                ]);
            }

            $pdo->commit();

            // Очищаем корзину
            unset($_SESSION['cart']);
            $success = "Your order has been placed successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to place order: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cart</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container mt-5">
<?php renderNavigation($_SESSION['user_role']); ?>
<h1>Your Cart</h1>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if (empty($cart)): ?>
    <p>Your cart is empty.</p>
<?php else: ?>
    <form method="POST" action="cart.php">
        <table class="table table-striped">
            <thead>
            <tr>
                <th>Name</th>
                <th>Price</th>
                <th>Quantity</th>
                <th>Total</th>
            </tr>
            </thead>
            <tbody>
            <?php $total = 0; ?>
            <?php foreach ($products as $product): ?>
                <?php
                $quantity = $cart[$product['id']];
                $subtotal = $product['price'] * $quantity;
                $total += $subtotal;
                ?>
                <tr>
                    <td><?= htmlspecialchars($product['name']) ?></td>
                    <td><?= htmlspecialchars($product['price']) ?></td>
                    <td><?= htmlspecialchars($quantity) ?></td>
                    <td><?= htmlspecialchars($subtotal) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
            <tr>
                <th colspan="3">Total</th>
                <th><?= htmlspecialchars($total) ?></th>
            </tr>
            </tfoot>
        </table>
        <button type="submit" name="place_order" class="btn btn-success">Place Order</button>
    </form>
<?php endif; ?>
</body>
</html>
