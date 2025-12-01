<?php
session_start();

require_once __DIR__ . '/../classes/db_connect.php';
require_once __DIR__ . '/../classes/UserLayout.php';

// Protect page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$transaction_id = isset($_GET['transaction_id']) ? htmlspecialchars($_GET['transaction_id']) : '';

if (!$order_id) {
    header("Location: shop.php");
    exit();
}

// Fetch order details
$stmt = $conn->prepare("SELECT o.*, u.email FROM orders o 
                       JOIN users u ON o.user_id = u.id 
                       WHERE o.id = ? AND o.user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    header("Location: shop.php");
    exit();
}

// Fetch order items
$stmt = $conn->prepare("SELECT oi.*, p.name, p.image_url 
                       FROM order_items oi 
                       JOIN products p ON oi.product_id = p.id 
                       WHERE oi.order_id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmed</title>
    <link rel="stylesheet" href="../navbar_sidebar.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }

        .success-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .success-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 20px rgba(40,167,69,0.3);
            text-align: center;
        }

        .success-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }

        .success-header h1 {
            margin: 0;
            font-size: 32px;
            font-weight: 800;
        }

        .success-header p {
            margin: 10px 0 0;
            opacity: 0.95;
            font-size: 18px;
        }

        .order-details {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: #333;
        }

        .detail-value {
            color: #666;
        }

        .transaction-id {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 5px;
            font-family: monospace;
            color: #495057;
        }

        .items-list {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .items-list h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #333;
        }

        .item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .item:last-child {
            border-bottom: none;
        }

        .item img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }

        .item-details {
            flex: 1;
        }

        .item-name {
            font-weight: 600;
            color: #333;
        }

        .item-qty {
            color: #666;
            font-size: 14px;
        }

        .item-price {
            color: #28a745;
            font-weight: bold;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .btn {
            padding: 15px 30px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            text-decoration: none;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102,126,234,0.4);
        }

        .btn-secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-secondary:hover {
            background: #f8f9ff;
        }
    </style>
</head>
<body>

<?php UserLayout::navbar(); ?>
<?php UserLayout::sidebar(); ?>

<div class="main-content">
    <div class="success-container">
        <div class="success-header">
            <div class="success-icon">✅</div>
            <h1>Order Confirmed!</h1>
            <p>Thank you for your purchase</p>
        </div>

        <div class="order-details">
            <h3 style="margin-top: 0; color: #333; margin-bottom: 20px;">Order Details</h3>
            
            <div class="detail-row">
                <span class="detail-label">Order ID:</span>
                <span class="detail-value">#<?= $order_id; ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Transaction ID:</span>
                <span class="transaction-id"><?= $transaction_id; ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Order Date:</span>
                <span class="detail-value"><?= date('F j, Y, g:i a', strtotime($order['created_at'])); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Payment Method:</span>
                <span class="detail-value"><?= ucfirst(str_replace('_', ' ', $order['payment_method'])); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Status:</span>
                <span class="detail-value">
                    <strong style="color: #ffc107;">⏳ Pending Confirmation</strong>
                </span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Email:</span>
                <span class="detail-value"><?= htmlspecialchars($order['email']); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Phone:</span>
                <span class="detail-value"><?= htmlspecialchars($order['phone']); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Delivery Address:</span>
                <span class="detail-value"><?= nl2br(htmlspecialchars($order['delivery_address'])); ?></span>
            </div>
        </div>

        <div class="items-list">
            <h3>Order Items</h3>
            
            <?php foreach ($items as $item): ?>
                <div class="item">
                    <img src="../<?= htmlspecialchars($item['image_url'] ?: 'uploads/default.jpg'); ?>" alt="Product">
                    <div class="item-details">
                        <div class="item-name"><?= htmlspecialchars($item['name']); ?></div>
                        <div class="item-qty">Quantity: <?= $item['quantity']; ?> × $<?= number_format($item['price'], 2); ?></div>
                    </div>
                    <div class="item-price">
                        $<?= number_format($item['quantity'] * $item['price'], 2); ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div class="detail-row" style="margin-top: 20px; padding-top: 20px; border-top: 2px solid #f0f0f0;">
                <span class="detail-label" style="font-size: 20px;">Total Amount:</span>
                <span style="font-size: 24px; font-weight: bold; color: #28a745;">
                    $<?= number_format($order['total_amount'], 2); ?>
                </span>
            </div>
        </div>

        <div class="action-buttons">
            <a href="Orders.php" class="btn btn-primary">View All Orders</a>
            <a href="shop.php" class="btn btn-secondary">Continue Shopping</a>
        </div>
    </div>
</div>

</body>
</html>