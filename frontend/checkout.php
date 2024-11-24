<?php
session_start();
require '../backend/db.php';

// Проверяем авторизацию и роль
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['customer', 'farmer', 'admin', 'moderator'])) {
    header('Location: login.php');
    exit;
}

$title = 'Checkout';
$customer_id = $_SESSION['user_id'];
$cart = $_SESSION['cart'] ?? []; // Получаем корзину из сессии

if (empty($cart)) {
    header('Location: cart.php');
    exit; // Если корзина пуста, перенаправляем на корзину
}

// Генерация CSRF токена
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$products = [];
$errors = [];
$total = 0;

// Проверяем товары в корзине
if (!empty($cart)) {
    $placeholders = implode(',', array_fill(0, count($cart), '?'));
    $query = "SELECT id, name, price, quantity FROM products WHERE id IN ($placeholders)";
    $stmt = $pdo->prepare($query);
    $stmt->execute(array_keys($cart));
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Обрабатываем отправку заказа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token.');
    }

    $order_items = [];
    foreach ($products as $product) {
        $product_id = $product['id'];
        $quantity = $cart[$product_id]['quantity'];

        // Проверяем доступное количество
        if ($quantity > $product['quantity']) {
            $errors[] = "Not enough stock for product: " . htmlspecialchars($product['name']);
        } else {
            $total_price = $product['price'] * $quantity;
            $order_items[] = [
                'product_id' => $product_id,
                'quantity' => $quantity,
                'price_per_unit' => $product['price'],
                'total_price' => $total_price
            ];
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Создаем заказ
            $query = "INSERT INTO orders (customer_id, status, order_date) VALUES (:customer_id, 'pending', NOW())";
            $stmt = $pdo->prepare($query);
            $stmt->execute(['customer_id' => $customer_id]);
            $order_id = $pdo->lastInsertId();

            // Добавляем товары в OrderItems
            $query = "INSERT INTO orderitems (order_id, product_id, quantity, price_per_unit) 
                      VALUES (:order_id, :product_id, :quantity, :price_per_unit)";
            $stmt = $pdo->prepare($query);

            foreach ($order_items as $item) {
                $stmt->execute([
                    'order_id' => $order_id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price_per_unit' => $item['price_per_unit']
                ]);

                // Обновляем количество товара
                $update_query = "UPDATE products SET quantity = quantity - :quantity WHERE id = :product_id";
                $update_stmt = $pdo->prepare($update_query);
                $update_stmt->execute([
                    'quantity' => $item['quantity'],
                    'product_id' => $item['product_id']
                ]);
            }

            $pdo->commit();

            // Очищаем корзину
            unset($_SESSION['cart']);
            $_SESSION['success'] = 'Your order has been placed successfully!';
            header('Location: my_orders.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Failed to place order: " . $e->getMessage();
        }
    }
}

// Генерация HTML
ob_start();
?>

<h1 class="text-center mb-4">Checkout</h1>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!empty($products)): ?>
    <table class="table table-bordered text-center">
        <thead>
            <tr>
                <th>Product</th>
                <th>Price</th>
                <th>Quantity</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $product): ?>
                <?php
                $product_id = $product['id'];
                $quantity = $cart[$product_id]['quantity'];
                $subtotal = $product['price'] * $quantity;
                $total += $subtotal;
                ?>
                <tr>
                    <td><?= htmlspecialchars($product['name']) ?></td>
                    <td>$<?= number_format($product['price'], 2) ?></td>
                    <td><?= htmlspecialchars($quantity) ?></td>
                    <td>$<?= number_format($subtotal, 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="3">Total</th>
                <th>$<?= number_format($total, 2) ?></th>
            </tr>
        </tfoot>
    </table>
    <form method="POST" action="checkout.php">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <button type="submit" name="place_order" class="btn btn-success">Place Order</button>
    </form>
<?php else: ?>
    <p>Your cart is empty. <a href="index.php">Continue shopping</a>.</p>
<?php endif; ?>

<?php
$content = ob_get_clean();
require '../interface/templates/layout.php';
?>
