<?php
session_start();

// Include database & layout
require_once __DIR__ . '/../classes/db_connect.php';
require_once __DIR__ . '/../classes/UserLayout.php';

// Protect page: only logged-in users
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit();
}

// Get user ID
$user_id = $_SESSION['user_id'];

// Handle GET request from shop.php "Add to Cart" link
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['product_id'])) {
    $product_id = intval($_GET['product_id']);
    
    if ($product_id > 0) {
        // Check if product exists and has stock
        $stmt = $conn->prepare("SELECT stock, name FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $product = $result->fetch_assoc();
            
            if ($product['stock'] >= 1) {
                // Check if already in cart
                $stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
                $stmt->bind_param("ii", $user_id, $product_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    // Update existing cart item
                    $cart_item = $result->fetch_assoc();
                    $new_quantity = $cart_item['quantity'] + 1;
                    
                    if ($new_quantity <= $product['stock']) {
                        $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
                        $stmt->bind_param("ii", $new_quantity, $cart_item['id']);
                        
                        if ($stmt->execute()) {
                            $_SESSION['cart_message'] = 'Cart updated successfully!';
                            $_SESSION['cart_message_type'] = 'success';
                        } else {
                            $_SESSION['cart_message'] = 'Failed to update cart';
                            $_SESSION['cart_message_type'] = 'error';
                        }
                    } else {
                        $_SESSION['cart_message'] = 'Not enough stock available';
                        $_SESSION['cart_message_type'] = 'error';
                    }
                } else {
                    // Add new cart item
                    $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)");
                    $stmt->bind_param("ii", $user_id, $product_id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['cart_message'] = 'Added to cart successfully!';
                        $_SESSION['cart_message_type'] = 'success';
                    } else {
                        $_SESSION['cart_message'] = 'Failed to add to cart';
                        $_SESSION['cart_message_type'] = 'error';
                    }
                }
            } else {
                $_SESSION['cart_message'] = 'Product out of stock';
                $_SESSION['cart_message_type'] = 'error';
            }
        } else {
            $_SESSION['cart_message'] = 'Product not found';
            $_SESSION['cart_message_type'] = 'error';
        }
    } else {
        $_SESSION['cart_message'] = 'Invalid product';
        $_SESSION['cart_message_type'] = 'error';
    }
    
    // Redirect to cart page to show updated cart
    header("Location: cart.php");
    exit();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // Add to cart
    if ($_POST['action'] === 'add') {
        $product_id = intval($_POST['product_id']);
        $quantity = intval($_POST['quantity']);
        
        if ($quantity < 1) {
            echo json_encode(['success' => false, 'message' => 'Invalid quantity']);
            exit();
        }
        
        // Check stock
        $stmt = $conn->prepare("SELECT stock FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Product not found']);
            exit();
        }
        
        $product = $result->fetch_assoc();
        
        if ($product['stock'] < $quantity) {
            echo json_encode(['success' => false, 'message' => 'Not enough stock available']);
            exit();
        }
        
        // Check if already in cart
        $stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $user_id, $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $cart_item = $result->fetch_assoc();
            $new_quantity = $cart_item['quantity'] + $quantity;
            
            if ($new_quantity > $product['stock']) {
                echo json_encode(['success' => false, 'message' => 'Total quantity would exceed available stock']);
                exit();
            }
            
            $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
            $stmt->bind_param("ii", $new_quantity, $cart_item['id']);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Cart updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update cart']);
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $user_id, $product_id, $quantity);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Added to cart successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add to cart']);
            }
        }
        exit();
    }
    
    // Update cart quantity
    if ($_POST['action'] === 'update') {
        $cart_id = intval($_POST['cart_id']);
        $quantity = intval($_POST['quantity']);
        
        $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("iii", $quantity, $cart_id, $user_id);
        
        if ($stmt->execute()) {
            $stmt = $conn->prepare("SELECT p.price FROM cart c JOIN products p ON c.product_id = p.id WHERE c.id = ?");
            $stmt->bind_param("i", $cart_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            $stmt = $conn->prepare("SELECT SUM(c.quantity * p.price) as total FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $total_row = $result->fetch_assoc();
            
            echo json_encode([
                'success' => true,
                'price' => $row['price'],
                'total' => $total_row['total']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update']);
        }
        exit();
    }
    
    // Remove from cart
    if ($_POST['action'] === 'remove') {
        $cart_id = intval($_POST['cart_id']);
        
        $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $cart_id, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to remove item']);
        }
        exit();
    }
}

// Fetch cart items with product details
$query = "SELECT c.id as cart_id, c.product_id, c.quantity, c.color_data,
          p.name, p.price, p.image_url, p.stock,
          (c.quantity * p.price) as subtotal
          FROM cart c
          JOIN products p ON c.product_id = p.id
          WHERE c.user_id = ?
          ORDER BY c.id DESC";

$stmt = $conn->prepare($query);

// Check if prepare failed
if (!$stmt) {
    die("Error preparing statement: " . $conn->error . "<br>Make sure the 'cart' table exists in your database.");
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

// Calculate total
$total = 0;
$cart_items = [];
while ($row = $result->fetch_assoc()) {
    $cart_items[] = $row;
    $total += $row['subtotal'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Shopping Cart</title>
    <link rel="stylesheet" href="../navbar_sidebar.css">
    <style>
        .cart-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .cart-header {
            background: linear-gradient(135deg, #fff 0%, #fff 100%);
            color: black;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 20px rgba(0,123,255,0.3);
            text-align: center;
            flex-grow: initial;
            font-weight: 8000;
        }

        .cart-header h2 {
            margin: 0;
            font-size: 32px;
        }

        .cart-item {
            display: flex;
            align-items: center;
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .cart-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .cart-item img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 10px;
            margin-right: 20px;
        }

        .item-details {
            flex: 1;
        }

        .item-details h3 {
            margin: 0 0 10px;
            color: #333;
            font-size: 20px;
        }

        .item-details .price {
            color: #007bff;
            font-weight: bold;
            font-size: 18px;
        }

        .item-details .stock-info {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }

        .color-info {
            color: #666;
            font-size: 14px;
            margin-top: 8px;
            padding: 8px;
            background: #f0f8ff;
            border-left: 3px solid #28a745;
            border-radius: 4px;
        }

        .color-tags {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 5px;
        }

        .color-tag {
            background: white;
            padding: 5px 10px;
            border-radius: 15px;
            border: 1px solid #ddd;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
        }

        .color-dot {
            width: 15px;
            height: 15px;
            border-radius: 50%;
            border: 1px solid #999;
            display: inline-block;
            flex-shrink: 0;
        }

        .color-name {
            color: #333;
            font-weight: 500;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-right: 20px;
        }

        .qty-btn {
            background: #007bff;
            color: white;
            border: none;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .qty-btn:hover {
            background: #0056b3;
            transform: scale(1.1);
        }

        .qty-btn.minus {
            background: #e74c3c;
        }

        .qty-btn.minus:hover {
            background: #c0392b;
        }

        .quantity {
            font-size: 18px;
            font-weight: bold;
            min-width: 40px;
            text-align: center;
        }

        .subtotal {
            font-size: 20px;
            font-weight: bold;
            color:black;
            margin-right: 20px;
            min-width: 100px;
            text-align: right;
        }

        .remove-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .remove-btn:hover {
            background: #c82333;
            transform: scale(1.05);
        }

        .cart-summary {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-top: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .cart-summary h3 {
            margin-top: 0;
            color: #333;
            font-size: 24px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 15px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin: 15px 0;
            font-size: 18px;
        }

        .summary-row.total {
            font-size: 24px;
            font-weight: bold;
            color: black;
            border-top: 2px solid #ddd;
            padding-top: 15px;
            margin-top: 20px;
        }

        .checkout-btn {
            width: 100%;
            background: linear-gradient(135deg, white 0%, whitesmoke 100%);
            color: black;
            border: none;
            padding: 15px;
            border-radius: 10px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 20px;
            transition: all 0.3s ease;
        }

        .checkout-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(40,167,69,0.4);
        }

        .empty-cart {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .empty-cart h3 {
            color: #666;
            font-size: 24px;
            margin-bottom: 20px;
        }

        .continue-shopping {
            display: inline-block;
            background: #fff;
            color: black;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-top: 12px;
        }

        .continue-shopping:hover {
            background: whitesmoke;
            transform: translateY(-2px);
        }

        .notification {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 9999;
            padding: 15px 25px;
            border-radius: 8px;
            color: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            animation: slideIn 0.3s ease;
        }

        .notification.success {
            background: #28a745;
        }

        .notification.error {
            background: #dc3545;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    </style>
</head>
<body>

<?php UserLayout::navbar(); ?>
<?php UserLayout::sidebar(); ?>

<div class="main-content">
    <?php if (isset($_SESSION['cart_message'])): ?>
        <div class="notification <?= $_SESSION['cart_message_type']; ?>">
            <?= htmlspecialchars($_SESSION['cart_message']); ?>
        </div>
        <script>
            setTimeout(() => {
                const notification = document.querySelector('.notification');
                if (notification) {
                    notification.style.display = 'none';
                }
            }, 3000);
        </script>
        <?php 
        unset($_SESSION['cart_message']);
        unset($_SESSION['cart_message_type']);
        ?>
    <?php endif; ?>

    <div class="cart-container">
        <div class="cart-header">
            <h2>🛒 Shopping Cart</h2>
            <p>Review your items and proceed to checkout</p>
        </div>

        <?php if (count($cart_items) > 0): ?>
            <?php foreach ($cart_items as $item): ?>
                <div class="cart-item">
                    <img src="../<?= htmlspecialchars($item['image_url'] ?: 'uploads/default.jpg'); ?>" alt="Product">
                    
                    <div class="item-details">
                        <h3><?= htmlspecialchars($item['name']); ?></h3>
                        <p class="price">$<?= number_format($item['price'], 2); ?> each</p>
                        <p class="stock-info">Available stock: <?= $item['stock']; ?></p>
                        
                        <!-- Color Information -->
                        <?php 
                        $colors_data = [];
                        if (!empty($item['color_data'])) {
                            $colors_data = json_decode($item['color_data'], true);
                        }
                        ?>
                        <?php if (!empty($colors_data) && count($colors_data) > 0) { ?>
                            <p class="color-info">
                                <strong>🎨 Colors Selected:</strong>
                                <span class="color-tags">
                                    <?php foreach ($colors_data as $idx => $color) { ?>
                                        <span class="color-tag">
                                            <span class="color-dot" style="background-color: <?= htmlspecialchars($color); ?>;"></span>
                                            <span class="color-name"><?= htmlspecialchars($color); ?></span>
                                        </span>
                                    <?php } ?>
                                </span>
                            </p>
                        <?php } ?>
                    </div>

                    <div class="quantity-controls">
                        <button class="qty-btn minus" onclick="updateCartQuantity(<?= $item['cart_id']; ?>, <?= $item['quantity']; ?>, -1, <?= $item['stock']; ?>)">−</button>
                        <span class="quantity" id="qty-<?= $item['cart_id']; ?>"><?= $item['quantity']; ?></span>
                        <button class="qty-btn plus" onclick="updateCartQuantity(<?= $item['cart_id']; ?>, <?= $item['quantity']; ?>, 1, <?= $item['stock']; ?>)">+</button>
                    </div>

                    <div class="subtotal" id="subtotal-<?= $item['cart_id']; ?>">
                        $<?= number_format($item['subtotal'], 2); ?>
                    </div>

                    <button class="remove-btn" onclick="removeFromCart(<?= $item['cart_id']; ?>)">Remove</button>
                </div>
            <?php endforeach; ?>

            <div class="cart-summary">
                <h3>Cart Summary</h3>
                <div class="summary-row">
                    <span>Items:</span>
                    <span><?= count($cart_items); ?></span>
                </div>
                <div class="summary-row total">
                    <span>Total:</span>
                    <span id="cart-total">$<?= number_format($total, 2); ?></span>
                </div>
                <button class="checkout-btn" onclick="proceedToCheckout()">Proceed to Checkout</button>
            </div>

        <?php else: ?>
            <div class="empty-cart">
                <h3>Your cart is empty</h3>
                <p>Start shopping to add items to your cart!</p>
                <a href="shop.php" class="continue-shopping">Continue Shopping</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function updateCartQuantity(cartId, currentQty, change, stock) {
    const newQty = currentQty + change;
    
    if (newQty < 1) {
        alert('Quantity cannot be less than 1. Use Remove button to delete item.');
        return;
    }
    
    if (newQty > stock) {
        alert('Not enough stock available!');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'update');
    formData.append('cart_id', cartId);
    formData.append('quantity', newQty);
    
    fetch('cart.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('qty-' + cartId).textContent = newQty;
            const subtotal = newQty * data.price;
            document.getElementById('subtotal-' + cartId).textContent = '$' + subtotal.toFixed(2);
            document.getElementById('cart-total').textContent = '$' + data.total.toFixed(2);
        } else {
            alert('Error updating cart: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating cart');
    });
}

function removeFromCart(cartId) {
    if (!confirm('Are you sure you want to remove this item?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'remove');
    formData.append('cart_id', cartId);
    
    fetch('cart.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error removing item: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error removing item');
    });
}

function proceedToCheckout() {
    // Redirect to order.php
    window.location.href = 'Orders.php';
}
</script>

</body>
</html>