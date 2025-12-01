<?php
// Include security FIRST (before session_start)
require_once __DIR__ . '/../config/security.php';

// Start session AFTER security config
session_start();

// Include database and layout
require_once __DIR__ . '/../classes/db_connect.php';
require_once __DIR__ . '/../classes/UserLayout.php';

// Protect page: only logged-in users
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if checkout info exists
if (!isset($_SESSION['checkout_phone']) || !isset($_SESSION['checkout_address'])) {
    header("Location: checkout.php");
    exit();
}

// Fetch cart items with colors
$stmt = $conn->prepare("SELECT c.id as cart_id, c.quantity, c.product_id, p.name, p.price, p.image_url, p.colors,
                       (c.quantity * p.price) as subtotal
                       FROM cart c
                       JOIN products p ON c.product_id = p.id
                       WHERE c.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_items = $stmt->get_result();

// Calculate total and store cart items
$total = 0;
$items = [];
while ($item = $cart_items->fetch_assoc()) {
    // Decode colors
    $item['colors_array'] = [];
    if (!empty($item['colors']) && $item['colors'] !== '0' && $item['colors'] !== 'NULL') {
        $decoded = json_decode($item['colors'], true);
        if (is_array($decoded) && count($decoded) > 0) {
            $item['colors_array'] = $decoded;
        }
    }
    $items[] = $item;
    $total += $item['subtotal'];
}

if (empty($items)) {
    header("Location: cart.php");
    exit();
}

// Get user info
$stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_info = $stmt->get_result()->fetch_assoc();

// Fetch payment settings from admin
$payment_query = $conn->query("SELECT * FROM payment_settings LIMIT 1");
$payment_settings = $payment_query && $payment_query->num_rows > 0 ? $payment_query->fetch_assoc() : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Payment</title>
    <link rel="stylesheet" href="../navbar_sidebar.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #fff 0%, #fff 100%);
            min-height: 100vh;
        }

        .main-content {
            padding: 20px;
            min-height: 100vh;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 10px;
            }
        }

        .payment-container-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px 0;
        }

        .payment-header {
            background: white;
            padding: 40px 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            text-align: center;
        }

        .payment-header h2 {
            margin: 0;
            font-size: 36px;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .payment-header p {
            margin-top: 10px;
            color: #666;
            font-size: 18px;
        }

        .security-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            padding: 12px 25px;
            border-radius: 50px;
            font-weight: 600;
            margin-top: 20px;
            box-shadow: 0 4px 15px rgba(21, 87, 36, 0.2);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: white;
            color: #667eea;
            text-decoration: none;
            margin-bottom: 20px;
            font-weight: 600;
            padding: 12px 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .back-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102,126,234,0.3);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .alert-error {
            background: #fff5f5;
            color: #c53030;
            border-left: 4px solid #f56565;
        }

        .payment-grid {
            display: grid;
            grid-template-columns: 1fr 450px;
            gap: 30px;
            margin-bottom: 30px;
        }

        @media (max-width: 1200px) {
            .payment-grid {
                grid-template-columns: 1fr;
            }
        }

        .payment-methods-section {
            background: white;
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }

        .section-header {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 3px solid #f0f0f0;
        }

        .payment-method {
            border: 3px solid #e0e0e0;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: white;
        }

        .payment-method:hover {
            border-color: #667eea;
            background: linear-gradient(135deg, #f8f9ff 0%, #f0f3ff 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102,126,234,0.2);
        }

        .payment-method.active {
            border-color: #667eea;
            background: linear-gradient(135deg, #f0f3ff 0%, #e8ecff 100%);
            box-shadow: 0 8px 25px rgba(102,126,234,0.3);
        }

        .payment-method-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .payment-icon {
            font-size: 40px;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
        }

        .payment-info h3 {
            font-size: 18px;
            color: #333;
            margin-bottom: 5px;
            font-weight: 700;
        }

        .payment-info p {
            font-size: 14px;
            color: #666;
        }

        .payment-radio {
            width: 24px;
            height: 24px;
            accent-color: #667eea;
            cursor: pointer;
            pointer-events: none;
        }

        .payment-details {
            display: none;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9ff 100%);
            border: 3px solid #667eea;
            border-radius: 15px;
            padding: 30px;
            margin-top: 20px;
            animation: slideDown 0.3s ease;
            box-shadow: 0 8px 25px rgba(102,126,234,0.2);
        }

        .payment-details.active {
            display: block;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: #333;
            font-weight: 600;
            font-size: 15px;
        }

        .form-group input {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: white;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102,126,234,0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .submit-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 18px;
            border-radius: 12px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
            margin-top: 20px;
            box-shadow: 0 8px 20px rgba(102,126,234,0.4);
        }

        .submit-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(102,126,234,0.5);
        }

        .info-box {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #666;
            border-left: 4px solid #667eea;
        }

        .info-box strong {
            color: #333;
            display: block;
            margin-bottom: 8px;
            font-size: 15px;
        }

        .order-summary {
            background: white;
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            position: sticky;
            top: 20px;
            max-height: calc(100vh - 40px);
            overflow-y: auto;
        }

        .order-summary h3 {
            margin-top: 0;
            color: #333;
            font-size: 26px;
            font-weight: 800;
            border-bottom: 3px solid #667eea;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }

        .summary-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 18px 0;
            border-bottom: 2px solid #f0f0f0;
        }

        .summary-item:last-child {
            border-bottom: none;
        }

        .summary-item img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            flex-shrink: 0;
        }

        .summary-item-details {
            flex: 1;
        }

        .summary-item-name {
            color: #333;
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 15px;
        }

        .summary-item-qty {
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .summary-item-colors {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .summary-color-circle {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 2px solid #ddd;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .summary-item-price {
            color: #667eea;
            font-weight: bold;
            font-size: 16px;
            flex-shrink: 0;
        }

        .summary-totals {
            margin-top: 25px;
            padding-top: 25px;
            border-top: 3px solid #f0f0f0;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin: 12px 0;
            font-size: 17px;
            color: #333;
        }

        .summary-row.total {
            font-size: 28px;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 3px solid #ddd;
        }

        .shipping-info-box {
            background: linear-gradient(135deg, #f8f9ff 0%, #f0f3ff 100%);
            border: 3px solid #e0e7ff;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
        }

        .shipping-info-box h4 {
            color: #333;
            margin-bottom: 18px;
            font-size: 18px;
            font-weight: 700;
        }

        .info-row {
            display: flex;
            gap: 12px;
            margin-bottom: 12px;
            font-size: 15px;
        }

        .info-label {
            font-weight: 600;
            color: #333;
            min-width: 85px;
        }

        .info-value {
            color: #666;
            flex: 1;
        }

        .edit-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 12px;
            transition: all 0.3s ease;
        }

        .edit-link:hover {
            color: #764ba2;
            transform: translateX(3px);
        }

        /* Scrollbar styling */
        .order-summary::-webkit-scrollbar {
            width: 8px;
        }

        .order-summary::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .order-summary::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
        }

        .order-summary::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }
    </style>
</head>
<body>

<?php UserLayout::navbar(); ?>
<?php UserLayout::sidebar(); ?>

<div class="main-content">
    <div class="payment-container-wrapper">
        <div class="payment-header">
            <h2>🔒 Secure Payment</h2>
            <p>Complete your order securely with encrypted payment processing</p>
            <div class="security-badge">
                <span>🛡️</span>
                <span>256-bit SSL Encryption Active</span>
            </div>
        </div>

        <a href="checkout.php" class="back-link">
            <span>←</span>
            <span>Back to Checkout</span>
        </a>

        <div class="payment-grid">
            <!-- Payment Methods Section -->
            <div class="payment-methods-section">
                <div class="section-header">💳 Choose Payment Method</div>

                <!-- Shipping Info Display -->
                <div class="shipping-info-box">
                    <h4>📍 Delivery Information</h4>
                    <div class="info-row">
                        <span class="info-label">📧 Email:</span>
                        <span class="info-value"><?= htmlspecialchars($user_info['email']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">📞 Phone:</span>
                        <span class="info-value"><?= htmlspecialchars($_SESSION['checkout_phone']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">📦 Address:</span>
                        <span class="info-value"><?= nl2br(htmlspecialchars($_SESSION['checkout_address'])); ?></span>
                    </div>
                    <a href="checkout.php" class="edit-link">
                        <span>✏️</span>
                        <span>Edit Information</span>
                    </a>
                </div>
                
                <!-- Card Payment -->
                <div class="payment-method" onclick="selectPayment('card')">
                    <div class="payment-method-left">
                        <div class="payment-icon">💳</div>
                        <div class="payment-info">
                            <h3>Credit/Debit Card</h3>
                            <p>Visa, Mastercard, American Express</p>
                        </div>
                    </div>
                    <input type="radio" name="payment_method_display" value="card" class="payment-radio" id="card-radio">
                </div>

                <!-- Card Payment Details -->
                <div class="payment-details" id="card-details">
                    <div class="info-box">
                        <strong>🔒 Security Notice:</strong>
                        Your payment information is encrypted with AES-256 encryption and secure. We recommend using payment gateways like Stripe or PayPal for production sites.
                    </div>

                    <form method="POST" action="confirm-payment.php">
                        <div class="form-group">
                            <label for="card_number">Card Number *</label>
                            <input type="text" id="card_number" name="card_number" placeholder="0000 0000 0000 0000" maxlength="19" required>
                        </div>

                        <div class="form-group">
                            <label for="card_name">Cardholder Name *</label>
                            <input type="text" id="card_name" name="card_name" placeholder="JOHN DOE" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="expiry">Expiration *</label>
                                <input type="text" id="expiry" name="expiry" placeholder="MM/YY" maxlength="5" required>
                            </div>
                            <div class="form-group">
                                <label for="cvv">CVV *</label>
                                <input type="text" id="cvv" name="cvv" placeholder="123" maxlength="3" required>
                            </div>
                        </div>

                        <input type="hidden" name="payment_method" value="card">
                        <input type="hidden" name="amount" value="<?= $total; ?>">

                        <button type="submit" name="submit_payment" class="submit-btn">
                            🔒 Pay Securely - $<?= number_format($total, 2); ?>
                        </button>
                    </form>
                </div>

                <!-- Bank Transfer -->
                <div class="payment-method" onclick="selectPayment('bank')">
                    <div class="payment-method-left">
                        <div class="payment-icon">🏦</div>
                        <div class="payment-info">
                            <h3>Bank Transfer</h3>
                            <p>Direct bank transfer</p>
                        </div>
                    </div>
                    <input type="radio" name="payment_method_display" value="bank_transfer" class="payment-radio" id="bank-radio">
                </div>

                <!-- Bank Transfer Details -->
                <div class="payment-details" id="bank-details">
                    <?php if ($payment_settings): ?>
                    <div class="info-box">
                        <strong>📋 Transfer to this account:</strong><br>
                        <strong>Bank:</strong> <?= htmlspecialchars($payment_settings['bank_name']); ?><br>
                        <strong>Account Name:</strong> <?= htmlspecialchars($payment_settings['account_name']); ?><br>
                        <strong>Account Number:</strong> <?= maskAccountNumber($payment_settings['account_number']); ?><br>
                        <?php if (!empty($payment_settings['payment_instructions'])): ?>
                        <br><strong>Instructions:</strong><br>
                        <?= nl2br(htmlspecialchars($payment_settings['payment_instructions'])); ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="confirm-payment.php">
                        <div class="form-group">
                            <label for="sender_account_number">Your Account Number *</label>
                            <input type="text" id="sender_account_number" name="account_number" placeholder="1234567890" required>
                        </div>

                        <div class="form-group">
                            <label for="sender_account_name">Your Account Name *</label>
                            <input type="text" id="sender_account_name" name="account_name" placeholder="John Doe" required>
                        </div>

                        <input type="hidden" name="payment_method" value="bank_transfer">
                        <input type="hidden" name="amount" value="<?= $total; ?>">

                        <button type="submit" name="submit_payment" class="submit-btn">
                            ✓ Confirm Transfer - $<?= number_format($total, 2); ?>
                        </button>
                    </form>
                </div>

                <?php if ($payment_settings && !empty($payment_settings['paypal_email'])): ?>
                <!-- PayPal -->
                <div class="payment-method" onclick="selectPayment('paypal')">
                    <div class="payment-method-left">
                        <div class="payment-icon">🅿️</div>
                        <div class="payment-info">
                            <h3>PayPal</h3>
                            <p>Pay with your PayPal account</p>
                        </div>
                    </div>
                    <input type="radio" name="payment_method_display" value="paypal" class="payment-radio" id="paypal-radio">
                </div>

                <!-- PayPal Details -->
                <div class="payment-details" id="paypal-details">
                    <div class="info-box">
                        <strong>💰 PayPal Payment</strong>
                        You will be redirected to PayPal to complete your payment securely.
                    </div>

                    <form method="POST" action="confirm-payment.php">
                        <div class="form-group">
                            <label for="paypal_email">Your PayPal Email *</label>
                            <input type="email" id="paypal_email" name="paypal_email" placeholder="your@email.com" required>
                        </div>

                        <input type="hidden" name="payment_method" value="paypal">
                        <input type="hidden" name="amount" value="<?= $total; ?>">

                        <button type="submit" name="submit_payment" class="submit-btn">
                            🅿️ Continue with PayPal - $<?= number_format($total, 2); ?>
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <!-- Order Summary Section -->
            <div class="order-summary">
                <h3>📦 Order Summary</h3>
                
                <?php foreach ($items as $item): ?>
                    <div class="summary-item">
                        <img src="../<?= htmlspecialchars($item['image_url'] ?: 'uploads/default.jpg'); ?>" alt="Product">
                        <div class="summary-item-details">
                            <div class="summary-item-name"><?= htmlspecialchars($item['name']); ?></div>
                            <div class="summary-item-qty">Qty: <?= $item['quantity']; ?></div>
                            
                            <!-- Display Colors if Available -->
                            <?php if (!empty($item['colors_array']) && is_array($item['colors_array'])): ?>
                                <div class="summary-item-colors">
                                    <?php foreach ($item['colors_array'] as $color): ?>
                                        <div class="summary-color-circle" 
                                             style="background-color: <?= htmlspecialchars($color); ?>;"
                                             title="<?= htmlspecialchars($color); ?>"></div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
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
                        <span style="color: #28a745; font-weight: 600;">Free</span>
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

<script>
    function selectPayment(method) {
        // Remove active class from all methods
        document.querySelectorAll('.payment-method').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.payment-details').forEach(el => el.classList.remove('active'));
        
        // Add active class to selected method
        event.currentTarget.classList.add('active');
        
        if (method === 'card') {
            document.getElementById('card-radio').checked = true;
            document.getElementById('card-details').classList.add('active');
        } else if (method === 'paypal') {
            document.getElementById('paypal-radio').checked = true;
            document.getElementById('paypal-details').classList.add('active');
        } else if (method === 'bank') {
            document.getElementById('bank-radio').checked = true;
            document.getElementById('bank-details').classList.add('active');
        }
    }

    // Card number formatting
    document.getElementById('card_number')?.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\s/g, '');
        let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
        e.target.value = formattedValue;
    });

    // Expiry formatting
    document.getElementById('expiry')?.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length >= 2) {
            value = value.slice(0, 2) + '/' + value.slice(2, 4);
        }
        e.target.value = value;
    });

    // CVV - numbers only
    document.getElementById('cvv')?.addEventListener('input', function(e) {
        e.target.value = e.target.value.replace(/\D/g, '');
    });
</script>

</body>
</html>