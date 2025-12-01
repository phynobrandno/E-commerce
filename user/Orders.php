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

$user_id = $_SESSION['user_id'];

// Check if cart is empty
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM cart WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$cart_count = $result->fetch_assoc()['count'];

if ($cart_count == 0) {
    header("Location: cart.php");
    exit();
}

// Handle checkout info submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proceed_to_payment'])) {
    $phone = trim($_POST['phone']);
    $delivery_address = trim($_POST['delivery_address']);
    
    // Validate inputs
    $errors = [];
    
    if (empty($phone)) {
        $errors[] = "Phone number is required";
    }
    
    if (empty($delivery_address)) {
        $errors[] = "Delivery address is required";
    }
    
    if (empty($errors)) {
        // Store checkout info in session
        $_SESSION['checkout_phone'] = $phone;
        $_SESSION['checkout_address'] = $delivery_address;
        
        // Redirect to payment page
        header("Location: payment-page.php");
        exit();
    }
}

// Fetch cart items for display with color data
$stmt = $conn->prepare("SELECT c.quantity, c.color_data, p.name, p.price, p.image_url, 
                       (c.quantity * p.price) as subtotal
                       FROM cart c
                       JOIN products p ON c.product_id = p.id
                       WHERE c.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_items = $stmt->get_result();

// Calculate total
$total = 0;
$items = [];
while ($item = $cart_items->fetch_assoc()) {
    $items[] = $item;
    $total += $item['subtotal'];
}

// Get user info for pre-filling
$stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_info = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Checkout</title>
    <link rel="stylesheet" href="../navbar_sidebar.css">
    <style>
        .checkout-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .checkout-header {
            background: linear-gradient(135deg, #fff 0%, #fff 100%);
            color: black;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 20px rgba(40,167,69,0.3);
            text-align: center;
        }

        .checkout-header h2 {
            margin: 0;
            font-size: 32px;
            font-weight: 800;
        }

        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
        }

        @media (max-width: 968px) {
            .checkout-grid {
                grid-template-columns: 1fr;
            }
        }

        .checkout-form {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section h3 {
            color: #333;
            font-size: 20px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
        }

        .order-summary {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            position: sticky;
            top: 20px;
        }

        .order-summary h3 {
            margin-top: 0;
            color: #333;
            font-size: 24px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .summary-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .summary-item:last-child {
            border-bottom: none;
        }

        .summary-item img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            flex-shrink: 0;
        }

        .summary-item-details {
            flex: 1;
        }

        .summary-item-name {
            color: #333;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .summary-item-qty {
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .summary-item-colors {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #e0e0e0;
        }

        .summary-item-colors-label {
            font-size: 12px;
            color: #666;
            font-weight: 600;
            margin-bottom: 6px;
            display: block;
        }

        .color-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #f0f8ff;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            border: 1px solid #ddd;
            margin-right: 6px;
            margin-bottom: 4px;
        }

        .color-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 1px solid #999;
            flex-shrink: 0;
        }

        .summary-item-price {
            color: #667eea;
            font-weight: bold;
            white-space: nowrap;
            margin-left: 10px;
        }

        .summary-totals {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            font-size: 16px;
        }

        .summary-row.total {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #ddd;
        }

        .payment-btn {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 18px;
            border-radius: 16px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 20px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 16px rgba(102, 126, 234, 0.4);
        }

        .payment-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.5);
        }

        .payment-btn:active {
            transform: translateY(0);
        }

        .payment-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }

        .error-message ul {
            margin: 10px 0 0 20px;
            padding: 0;
        }

        .back-to-cart {
            display: inline-block;
            color: #667eea;
            text-decoration: none;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .back-to-cart:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<?php UserLayout::navbar(); ?>
<?php UserLayout::sidebar(); ?>

<div class="main-content">
    <div class="checkout-container">
        <div class="checkout-header">
            <h2>🛒 Checkout</h2>
            <p>Complete your order information</p>
        </div>

        <a href="cart.php" class="back-to-cart">← Back to Cart</a>

        <?php if (!empty($errors)): ?>
            <div class="error-message">
                <strong>Please fix the following errors:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="checkout-grid">
            <div class="checkout-form">
                <form method="POST" action="" id="checkout-form">
                    <div class="form-section">
                        <h3>📞 Contact Information</h3>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" value="<?= htmlspecialchars($user_info['email']); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone Number *</label>
                            <input type="tel" id="phone" name="phone" required 
                                   value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : (isset($_SESSION['checkout_phone']) ? htmlspecialchars($_SESSION['checkout_phone']) : ''); ?>" 
                                   placeholder="Enter your phone number">
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>📍 Delivery Address</h3>
                        <div class="form-group">
                            <label for="delivery_address">Full Address *</label>
                            <textarea id="delivery_address" name="delivery_address" required 
                                      placeholder="Enter your full delivery address (street, city, state, zip code)"><?= isset($_POST['delivery_address']) ? htmlspecialchars($_POST['delivery_address']) : (isset($_SESSION['checkout_address']) ? htmlspecialchars($_SESSION['checkout_address']) : ''); ?></textarea>
                        </div>
                    </div>

                    <button type="submit" name="proceed_to_payment" class="payment-btn">
                        Proceed to Payment →
                    </button>
                </form>
            </div>

            <div class="order-summary">
                <h3>Order Summary</h3>
                
                <?php foreach ($items as $item): ?>
                    <div class="summary-item">
                        <img src="../<?= htmlspecialchars($item['image_url'] ?: 'uploads/default.jpg'); ?>" alt="Product">
                        <div class="summary-item-details">
                            <div class="summary-item-name"><?= htmlspecialchars($item['name']); ?></div>
                            <div class="summary-item-qty">Qty: <?= $item['quantity']; ?></div>
                            
                            <!-- Display Colors if available -->
                            <?php 
                            $colors_data = [];
                            if (!empty($item['color_data'])) {
                                $colors_data = json_decode($item['color_data'], true);
                            }
                            ?>
                            <?php if (!empty($colors_data) && count($colors_data) > 0) { ?>
                                <div class="summary-item-colors">
                                    <span class="summary-item-colors-label">🎨 Colors:</span>
                                    <div>
                                        <?php foreach ($colors_data as $color) { ?>
                                            <span class="color-badge">
                                                <span class="color-dot" style="background-color: <?= htmlspecialchars($color); ?>;"></span>
                                                <span><?= htmlspecialchars($color); ?></span>
                                            </span>
                                        <?php } ?>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="summary-item-price">
                            $<?= number_format($item['subtotal'], 2); ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="summary-totals">
                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <span>$<?= number_format($total, 2); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Shipping:</span>
                        <span>Free</span>
                    </div>
                    <div class="summary-row total">
                        <span>Total:</span>
                        <span>$<?= number_format($total, 2); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>