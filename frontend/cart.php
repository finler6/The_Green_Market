<?php
session_start();
require '../backend/db.php';

$title = 'Your Shopping Cart';

$cart = $_SESSION['cart'] ?? [];

$total = 0;

ob_start();
?>

<h1 class="text-center mb-4">Your Shopping Cart</h1>

<?php if (!empty($cart)): ?>
    <table class="table table-bordered text-center">
        <thead>
            <tr>
                <th>Product</th>
                <th>Price</th>
                <th>Quantity</th>
                <th>Total</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cart as $product_id => $item): ?>
                <?php $item_total = $item['price'] * $item['quantity']; ?>
                <tr>
                <td>
                    <a href="product.php?id=<?= htmlspecialchars($product_id) ?>" class="product-link">
                        <?= htmlspecialchars($item['name']) ?>
                    </a>
                </td>
                    <td>$<?= number_format($item['price'], 2) ?></td>
                    <td>
                        <form method="POST" action="update_cart.php" style="display: inline;">
                            <input 
                                type="number" 
                                name="quantity" 
                                value="<?= htmlspecialchars($_SESSION['cart'][$product_id]['quantity']) ?>" 
                                min="1" 
                                max="<?= htmlspecialchars($product['quantity']) ?>" 
                                style="width: 60px; text-align: center;" 
                                onchange="this.form.submit()">
                            <input type="hidden" name="product_id" value="<?= htmlspecialchars($product_id) ?>">
                        </form>
                    </td>
                    <td>$<?= number_format($item_total, 2) ?></td>
                    <td>
                        <form method="POST" action="update_cart.php" style="display:inline;">
                            <input type="hidden" name="product_id" value="<?= $product_id ?>">
                            <button type="submit" name="action" value="increase" class="btn btn-sm btn-add">+</button>
                            <button type="submit" name="action" value="decrease" class="btn btn-sm btn-remove">-</button>
                        </form>
                        <form method="POST" action="update_cart.php" style="display:inline;">
                            <input type="hidden" name="product_id" value="<?= $product_id ?>">
                            <button type="submit" name="action" value="remove" class="btn btn-sm btn-delete">Remove</button>
                        </form>
                    </td>
                </tr>
                <?php $total += $item_total; ?>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="text-end">
        <h4>Total: $<?= number_format($total, 2) ?></h4>
        <a href="checkout.php" class="btn btn-success">Checkout</a>
    </div>
<?php else: ?>
    <p>Your cart is empty. <a href="index.php">Continue shopping</a>.</p>
<?php endif; ?>

<?php
$content = ob_get_clean();
require '../interface/templates/layout.php';
?>
