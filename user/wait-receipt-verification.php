<?php
// wait-receipt-verification.php
require_once __DIR__ . '/../config/security.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../classes/db_connect.php';
require_once __DIR__ . '/../classes/UserLayout.php';

// Protect page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = $_GET['order_id'] ?? null;
$transaction_id = $_GET['transaction_id'] ?? null;

if (!$order_id) {
    header("Location: cart.php");
    exit();
}

// Fetch order and payment details
$stmt = $conn->prepare("SELECT o.id, o.total_amount, o.delivery_address, o.phone, o.payment_method, o.status, o.created_at,
                              p.id as payment_id, p.receipt_image, p.created_at as payment_created_at
                       FROM orders o
                       LEFT JOIN payments p ON o.id = p.order_id
                       WHERE o.id = ? AND o.user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    header("Location: cart.php");
    exit();
}

// Get user email
$user_stmt = $conn->prepare("SELECT email, username FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_info = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

// Calculate time passed and remaining
$payment_time = new DateTime($order['payment_created_at']);
$deadline = clone $payment_time;
$deadline->modify('+24 hours');
$now = new DateTime();

$time_diff = $now->diff($deadline);
$hours_remaining = $time_diff->h;
$minutes_remaining = $time_diff->i;
$seconds_remaining = $time_diff->s;
$is_overdue = $time_diff->invert == 1;

// Determine status
$status = $order['status'];
$is_approved = ($status === 'completed');
$is_rejected = ($status === 'cancelled');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waiting for Payment Verification</title>
    <link rel="stylesheet" href="../navbar_sidebar.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .main-content {
            padding: 20px;
            min-height: 100vh;
        }

        .container {
            max-width: 800px;
            margin: 40px auto;
        }

        /* WAITING STATUS */
        .waiting-card {
            background: white;
            border-radius: 20px;
            padding: 50px 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            text-align: center;
            margin-bottom: 30px;
        }

        .waiting-icon {
            font-size: 100px;
            margin-bottom: 20px;
            animation: spin 3s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .waiting-title {
            font-size: 32px;
            font-weight: 800;
            color: #333;
            margin-bottom: 15px;
        }

        .waiting-subtitle {
            font-size: 18px;
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        /* TIMER */
        .timer-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 30px;
            color: white;
            margin-bottom: 30px;
        }

        .timer-label {
            font-size: 14px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .timer-display {
            font-size: 56px;
            font-weight: bold;
            font-family: 'Courier New', monospace;
            margin-bottom: 15px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        .timer-message {
            font-size: 16px;
            line-height: 1.8;
            opacity: 0.95;
        }

        /* INSTRUCTIONS */
        .instructions-box {
            background: #fff9e6;
            border: 3px solid #ffc107;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .instructions-box h3 {
            color: #856404;
            font-size: 20px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .instructions-box ol {
            color: #856404;
            line-height: 2;
            margin-left: 20px;
        }

        .instructions-box li {
            margin-bottom: 10px;
        }

        /* ORDER INFO */
        .order-info-box {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            border: 2px solid #e0e0e0;
        }

        .order-info-box h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 18px;
            font-weight: 700;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #333;
        }

        .info-value {
            color: #666;
            text-align: right;
        }

        /* APPROVED STATUS */
        .approved-card {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            border-radius: 20px;
            padding: 50px 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            text-align: center;
            margin-bottom: 30px;
            color: white;
        }

        .approved-icon {
            font-size: 100px;
            margin-bottom: 20px;
            animation: bounce 1s infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }

        .approved-title {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 15px;
        }

        .approved-message {
            font-size: 18px;
            line-height: 1.8;
        }

        /* REJECTED STATUS */
        .rejected-card {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            border-radius: 20px;
            padding: 50px 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            text-align: center;
            margin-bottom: 30px;
            color: white;
        }

        .rejected-icon {
            font-size: 100px;
            margin-bottom: 20px;
        }

        .rejected-title {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 15px;
        }

        .rejected-message {
            font-size: 18px;
            line-height: 1.8;
        }

        /* BUTTONS */
        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            flex: 1;
            padding: 15px 20px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(102,126,234,0.4);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        /* OVERDUE ALERT */
        .overdue-alert {
            background: #fff3e0;
            border: 3px solid #f59e0b;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .overdue-alert h3 {
            color: #92400e;
            font-size: 20px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .overdue-alert p {
            color: #92400e;
            line-height: 1.8;
        }
    </style>
</head>
<body>

<?php UserLayout::navbar(); ?>
<?php UserLayout::sidebar(); ?>

<div class="main-content">
    <div class="container">

        <?php if ($is_approved): ?>
            <!-- APPROVED STATUS -->
            <div class="approved-card">
                <div class="approved-icon">✅</div>
                <h1 class="approved-title">Payment Approved!</h1>
                <p class="approved-message">
                    Congratulations! Your payment has been verified and approved.<br>
                    Your order will be processed immediately.
                </p>
            </div>

            <div class="order-info-box">
                <h3>📦 Order Details</h3>
                <div class="info-item">
                    <span class="info-label">Order ID:</span>
                    <span class="info-value">#<?= $order['id']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Amount Paid:</span>
                    <span class="info-value" style="color: #22c55e; font-weight: 700;">$<?= number_format($order['total_amount'], 2); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Transaction ID:</span>
                    <span class="info-value"><?= htmlspecialchars($transaction_id); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Delivery Address:</span>
                    <span class="info-value"><?= nl2br(htmlspecialchars($order['delivery_address'])); ?></span>
                </div>
            </div>

            <div class="button-group">
                <a href="my-orders.php" class="btn btn-primary">📦 View My Orders</a>
                <a href="shop.php" class="btn btn-secondary">🛍️ Continue Shopping</a>
            </div>

        <?php elseif ($is_rejected): ?>
            <!-- REJECTED STATUS -->
            <div class="rejected-card">
                <div class="rejected-icon">❌</div>
                <h1 class="rejected-title">Payment Could Not Be Verified</h1>
                <p class="rejected-message">
                    Unfortunately, your payment could not be verified.<br>
                    Please contact our support team for assistance.
                </p>
            </div>

            <div class="order-info-box">
                <h3>📋 Order Information</h3>
                <div class="info-item">
                    <span class="info-label">Order ID:</span>
                    <span class="info-value">#<?= $order['id']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Order Total:</span>
                    <span class="info-value">$<?= number_format($order['total_amount'], 2); ?></span>
                </div>
            </div>

            <div class="instructions-box">
                <h3>📞 What to Do Next?</h3>
                <ol>
                    <li><strong>Contact our support team</strong> to know why your payment was rejected</li>
                    <li><strong>Verify your payment receipt</strong> - ensure it matches the order amount</li>
                    <li><strong>Try submitting again</strong> with a clearer receipt image</li>
                    <li><strong>Use a different payment method</strong> if available</li>
                </ol>
            </div>

            <div class="button-group">
                <a href="support-tickets.php" class="btn btn-primary">💬 Contact Support</a>
                <a href="my-orders.php" class="btn btn-secondary">📦 View Orders</a>
            </div>

        <?php else: ?>
            <!-- WAITING STATUS -->
            <div class="waiting-card">
                <div class="waiting-icon">⏳</div>
                <h1 class="waiting-title">Payment Under Review</h1>
                <p class="waiting-subtitle">
                    Your payment receipt has been received and is awaiting verification.<br>
                    Please wait while our team reviews your payment.
                </p>
            </div>

            <!-- TIMER -->
            <div class="timer-box" id="timerBox">
                <div class="timer-label">⏱️ Time Until Auto-Notification</div>
                <div class="timer-display" id="timerDisplay">24:00:00</div>
                <div class="timer-message">
                    If your payment is not verified within <strong>24 hours</strong>, please contact our support team immediately to inquire about the delay or rejection.
                </div>
            </div>

            <!-- INSTRUCTIONS -->
            <div class="instructions-box">
                <h3>📋 Important Information</h3>
                <ol>
                    <li><strong>Do NOT make another payment</strong> - your receipt is already in our system</li>
                    <li><strong>Wait for email notification</strong> - we will send you confirmation when approved</li>
                    <li><strong>Check your email</strong> (including spam folder) for updates</li>
                    <li><strong>After 24 hours:</strong> If not approved, contact support immediately</li>
                </ol>
            </div>

            <!-- OVERDUE ALERT (if time exceeded 24 hours) -->
            <?php if ($is_overdue): ?>
                <div class="overdue-alert">
                    <h3>⚠️ Verification Delayed</h3>
                    <p>
                        Your payment verification has taken longer than 24 hours.<br>
                        <strong>Please contact our support team NOW to check the status of your payment.</strong>
                    </p>
                </div>
            <?php endif; ?>

            <!-- ORDER INFO -->
            <div class="order-info-box">
                <h3>📦 Your Order</h3>
                <div class="info-item">
                    <span class="info-label">Order ID:</span>
                    <span class="info-value">#<?= $order['id']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Order Total:</span>
                    <span class="info-value">$<?= number_format($order['total_amount'], 2); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Transaction ID:</span>
                    <span class="info-value"><?= htmlspecialchars($transaction_id); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Delivery Address:</span>
                    <span class="info-value"><?= nl2br(htmlspecialchars($order['delivery_address'])); ?></span>
                </div>
            </div>

            <!-- BUTTONS -->
            <div class="button-group">
                <a href="support-tickets.php" class="btn btn-primary">💬 Contact Support</a>
                <a href="shop.php" class="btn btn-secondary">🛍️ Continue Shopping</a>
            </div>

        <?php endif; ?>

    </div>
</div>

<script>
    // Timer countdown (only if waiting)
    <?php if (!$is_approved && !$is_rejected): ?>
    let hours = <?= $hours_remaining; ?>;
    let minutes = <?= $minutes_remaining; ?>;
    let seconds = <?= $seconds_remaining; ?>;

    function updateTimer() {
        const display = document.getElementById('timerDisplay');
        if (display) {
            display.textContent = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        }

        if (seconds > 0) {
            seconds--;
        } else if (minutes > 0) {
            minutes--;
            seconds = 59;
        } else if (hours > 0) {
            hours--;
            minutes = 59;
            seconds = 59;
        }
    }

    // Update every second
    setInterval(updateTimer, 1000);

    // Check payment status every 30 seconds
    setInterval(() => {
        fetch('check-payment-status.php?order_id=<?= $order_id; ?>')
            .then(res => res.json())
            .then(data => {
                if (data.status === 'completed' || data.status === 'cancelled') {
                    location.reload();
                }
            })
            .catch(err => console.error('Status check error:', err));
    }, 30000);
    <?php endif; ?>
</script>

</body>
</html>