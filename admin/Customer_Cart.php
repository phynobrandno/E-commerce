<?php
session_start();

// Include database & layout
require_once __DIR__ . '/../classes/db_connect.php';
require_once __DIR__ . '/../classes/Layout.php';

// Protect page: only admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // Update cart quantity
    if ($_POST['action'] === 'update') {
        $cart_id = intval($_POST['cart_id']);
        $quantity = intval($_POST['quantity']);
        
        if ($quantity < 1) {
            echo json_encode(['success' => false, 'message' => 'Quantity must be at least 1']);
            exit();
        }
        
        // Check stock availability
        $stmt = $conn->prepare("SELECT p.stock FROM cart c JOIN products p ON c.product_id = p.id WHERE c.id = ?");
        $stmt->bind_param("i", $cart_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Cart item not found']);
            exit();
        }
        
        $row = $result->fetch_assoc();
        if ($quantity > $row['stock']) {
            echo json_encode(['success' => false, 'message' => 'Quantity exceeds available stock']);
            exit();
        }
        
        $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
        $stmt->bind_param("ii", $quantity, $cart_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Cart updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update cart']);
        }
        exit();
    }
    
    // Remove from cart
    if ($_POST['action'] === 'remove') {
        $cart_id = intval($_POST['cart_id']);
        
        $stmt = $conn->prepare("DELETE FROM cart WHERE id = ?");
        $stmt->bind_param("i", $cart_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Item removed successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to remove item']);
        }
        exit();
    }
    
    // Clear entire user cart
    if ($_POST['action'] === 'clear_cart') {
        $user_id = intval($_POST['user_id']);
        
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Cart cleared successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to clear cart']);
        }
        exit();
    }
}

// Fetch all cart items with user and product details
$query = "SELECT c.id as cart_id, c.user_id, c.product_id, c.quantity, c.created_at,
          u.username, u.email,
          p.name as product_name, p.price, p.image_url, p.stock,
          (c.quantity * p.price) as subtotal
          FROM cart c
          JOIN users u ON c.user_id = u.id
          JOIN products p ON c.product_id = p.id
          ORDER BY u.username ASC, c.created_at DESC";

$result = $conn->query($query);

// Group carts by user
$user_carts = [];
$total_carts = 0;
while ($row = $result->fetch_assoc()) {
    $user_id = $row['user_id'];
    if (!isset($user_carts[$user_id])) {
        $user_carts[$user_id] = [
            'username' => $row['username'],
            'email' => $row['email'],
            'items' => [],
            'total' => 0
        ];
    }
    $user_carts[$user_id]['items'][] = $row;
    $user_carts[$user_id]['total'] += $row['subtotal'];
    $total_carts++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Cart Management</title>
    <link rel="stylesheet" href="../navbar_sidebar.css">
    <style>
        .content {
             margin-left: 100px;
            padding: 30px;
            max-width: 1600px; /* ✅ Keeps consistent width */
            width: 100%;
            box-sizing: border-box;
        }

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 20px rgba(102,126,234,0.3);
                        margin-top: 70px;

        }

        .page-header h1 {
            margin: 0 0 10px;
            font-size: 32px;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-card .number {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
            margin: 10px 0;
        }

        .stat-card .label {
            color: #666;
            font-size: 14px;
        }

        .user-cart-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 5px solid #667eea;
        }

        .user-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .user-info h3 {
            margin: 0 0 5px;
            color: #333;
            font-size: 22px;
        }

        .user-info p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }

        .user-actions {
            display: flex;
            gap: 10px;
        }

        .clear-cart-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .clear-cart-btn:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .cart-item {
            display: flex;
            align-items: center;
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .cart-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }

        .cart-item img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            margin-right: 20px;
        }

        .item-details {
            flex: 1;
        }

        .item-details h4 {
            margin: 0 0 5px;
            color: #333;
            font-size: 20px;
        }

        .item-details .price {
            color: #667eea;
            font-weight: bold;
            font-size: 14px;
        }

        .item-details .stock-info {
            color: #666;
            font-size: 12px;
            margin-top: 3px;
        }

        .item-details .added-date {
            color: #999;
            font-size: 11px;
            margin-top: 3px;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-right: 20px;
        }

        .qty-input {
            width: 60px;
            padding: 8px;
            border: 2px solid #ddd;
            border-radius: 6px;
            text-align: center;
            font-size: 14px;
            font-weight: bold;
        }

        .update-btn, .remove-btn {
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 13px;
        }

        .update-btn {
            background: #28a745;
            color: white;
        }

        .update-btn:hover {
            background: #218838;
        }

        .remove-btn {
            background: #dc3545;
            color: white;
        }

        .remove-btn:hover {
            background: #c82333;
        }

        .subtotal {
            font-size: 16px;
            font-weight: bold;
            color: #28a745;
            min-width: 100px;
            text-align: right;
            margin-right: 15px;
        }

        .cart-total {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
            text-align: right;
        }

        .cart-total .total-label {
            font-size: 16px;
            color: #666;
        }

        .cart-total .total-amount {
            font-size: 24px;
            font-weight: bold;
            color: #28a745;
            margin-left: 10px;
        }

        .empty-cart {
            text-align: center;
            padding: 40px;
            color: #999;
            font-style: italic;
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

        .search-filter {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .search-filter input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }

        .search-filter input:focus {
            outline: none;
            border-color: #667eea;
        }
    </style>
</head>
<body>

<?php Layout::navbar(); ?>
<?php Layout::sidebar(); ?>

<div class="content">
    <div class="page-header">
        <h1>🛒 Customer Cart Management</h1>
        <p>View and manage all customer shopping carts</p>
    </div>

    <div class="stats-container">
        <div class="stat-card">
            <div class="label">Total Customers with Carts</div>
            <div class="number"><?= count($user_carts); ?></div>
        </div>
        <div class="stat-card">
            <div class="label">Total Cart Items</div>
            <div class="number"><?= $total_carts; ?></div>
        </div>
        <div class="stat-card">
            <div class="label">Total Cart Value</div>
            <div class="number">$<?= number_format(array_sum(array_column($user_carts, 'total')), 2); ?></div>
        </div>
    </div>

    <div class="search-filter">
        <input type="text" id="searchUser" placeholder="🔍 Search by username or email..." onkeyup="filterUsers()">
    </div>

    <?php if (count($user_carts) > 0): ?>
        <?php foreach ($user_carts as $user_id => $cart_data): ?>
            <div class="user-cart-section" data-username="<?= strtolower($cart_data['username']); ?>" data-email="<?= strtolower($cart_data['email']); ?>">
                <div class="user-header">
                    <div class="user-info">
                        <h3><?= htmlspecialchars($cart_data['username']); ?></h3>
                        <p><?= htmlspecialchars($cart_data['email']); ?> • <?= count($cart_data['items']); ?> items</p>
                    </div>
                    <div class="user-actions">
                        <button class="clear-cart-btn" onclick="clearUserCart(<?= $user_id; ?>, '<?= htmlspecialchars($cart_data['username']); ?>')">
                            Clear Cart
                        </button>
                    </div>
                </div>

                <?php foreach ($cart_data['items'] as $item): ?>
                    <div class="cart-item" id="cart-item-<?= $item['cart_id']; ?>">
                        <img src="../<?= htmlspecialchars($item['image_url'] ?: 'uploads/default.jpg'); ?>" alt="Product">
                        
                        <div class="item-details">
                            <h4><?= htmlspecialchars($item['product_name']); ?></h4>
                            <p class="price">$<?= number_format($item['price'], 2); ?> each</p>
                            <p class="stock-info">Available stock: <?= $item['stock']; ?></p>
                            <p class="added-date">Added: <?= date('M d, Y H:i', strtotime($item['created_at'])); ?></p>
                        </div>

                        <div class="quantity-controls">
                            <input type="number" 
                                   class="qty-input" 
                                   id="qty-<?= $item['cart_id']; ?>" 
                                   value="<?= $item['quantity']; ?>" 
                                   min="1" 
                                   max="<?= $item['stock']; ?>">
                            <button class="update-btn" onclick="updateQuantity(<?= $item['cart_id']; ?>, <?= $item['stock']; ?>)">
                                Update
                            </button>
                        </div>

                        <div class="subtotal" id="subtotal-<?= $item['cart_id']; ?>">
                            $<?= number_format($item['subtotal'], 2); ?>
                        </div>

                        <button class="remove-btn" onclick="removeItem(<?= $item['cart_id']; ?>)">
                            Remove
                        </button>
                    </div>
                <?php endforeach; ?>

                <div class="cart-total">
                    <span class="total-label">Cart Total:</span>
                    <span class="total-amount" id="user-total-<?= $user_id; ?>">$<?= number_format($cart_data['total'], 2); ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="user-cart-section">
            <div class="empty-cart">
                <h3>No customer carts found</h3>
                <p>There are currently no items in any customer carts.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

function updateQuantity(cartId, maxStock) {
    const qtyInput = document.getElementById('qty-' + cartId);
    const quantity = parseInt(qtyInput.value);
    
    if (quantity < 1) {
        showNotification('Quantity must be at least 1', 'error');
        return;
    }
    
    if (quantity > maxStock) {
        showNotification('Quantity exceeds available stock', 'error');
        qtyInput.value = maxStock;
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'update');
    formData.append('cart_id', cartId);
    formData.append('quantity', quantity);
    
    fetch('Customer_Cart.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Cart updated successfully', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error updating cart', 'error');
    });
}

function removeItem(cartId) {
    if (!confirm('Are you sure you want to remove this item from the customer\'s cart?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'remove');
    formData.append('cart_id', cartId);
    
    fetch('Customer_Cart.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Item removed successfully', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error removing item', 'error');
    });
}

function clearUserCart(userId, username) {
    if (!confirm(`Are you sure you want to clear all items from ${username}'s cart?`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'clear_cart');
    formData.append('user_id', userId);
    
    fetch('Customer_Cart.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Cart cleared successfully', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error clearing cart', 'error');
    });
}

function filterUsers() {
    const searchTerm = document.getElementById('searchUser').value.toLowerCase();
    const userSections = document.querySelectorAll('.user-cart-section');
    
    userSections.forEach(section => {
        const username = section.getAttribute('data-username');
        const email = section.getAttribute('data-email');
        
        if (username && email) {
            if (username.includes(searchTerm) || email.includes(searchTerm)) {
                section.style.display = 'block';
            } else {
                section.style.display = 'none';
            }
        }
    });
}
</script>

</body>
</html>