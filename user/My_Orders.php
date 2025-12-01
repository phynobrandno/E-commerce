<?php
// my-orders.php
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

// Fetch all orders with payment info
$orders_query = $conn->prepare("
    SELECT o.id, o.total_amount, o.status, o.payment_method, o.phone, 
           o.delivery_address, o.created_at, o.updated_at,
           p.id as payment_id, p.receipt_image, p.transaction_id, 
           p.status as payment_status, p.created_at as payment_date
    FROM orders o
    LEFT JOIN payments p ON o.id = p.order_id
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC
");
$orders_query->bind_param("i", $user_id);
$orders_query->execute();
$orders_result = $orders_query->get_result();
$orders = [];

while ($order = $orders_result->fetch_assoc()) {
    $orders[] = $order;
}
$orders_query->close();

// Function to get order items with colors
function getOrderItems($conn, $order_id) {
    $items_query = $conn->prepare("
        SELECT oi.quantity, oi.price, p.name, p.image_url, p.colors,
               (oi.quantity * oi.price) as subtotal
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $items_query->bind_param("i", $order_id);
    $items_query->execute();
    $result = $items_query->get_result();
    $items = [];
    
    while ($item = $result->fetch_assoc()) {
        // Decode colors
        $item['colors_array'] = [];
        if (!empty($item['colors']) && $item['colors'] !== '0' && $item['colors'] !== 'NULL') {
            $decoded = json_decode($item['colors'], true);
            if (is_array($decoded) && count($decoded) > 0) {
                $item['colors_array'] = $decoded;
            }
        }
        $items[] = $item;
    }
    $items_query->close();
    
    return $items;
}

// Get success message if redirected from payment
$success_message = $_SESSION['payment_success'] ?? null;
unset($_SESSION['payment_success']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders</title>
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

        .container {
            max-width: 1200px;
            margin: 40px auto;
        }

        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .page-header h1 {
            font-size: 36px;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #666;
            font-size: 16px;
        }

        /* Success Alert */
        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border-left: 4px solid #22c55e;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            font-weight: 500;
        }

        /* Empty State */
        .empty-state {
            background: white;
            border-radius: 20px;
            padding: 60px 40px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }

        .empty-state-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }

        .empty-state h2 {
            font-size: 24px;
            color: #333;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #666;
            margin-bottom: 30px;
        }

        .btn-shop {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-shop:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(102,126,234,0.4);
        }

        /* Order Card */
        .order-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }

        .order-card:hover {
            box-shadow: 0 15px 50px rgba(0,0,0,0.15);
            transform: translateY(-5px);
        }

        /* Order Header */
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .order-info {
            flex: 1;
        }

        .order-id {
            font-size: 20px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .order-date {
            color: #666;
            font-size: 14px;
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background: #fff9e6;
            color: #856404;
            border: 2px solid #ffc107;
        }

        .status-completed {
            background: #f0fdf4;
            color: #166534;
            border: 2px solid #22c55e;
        }

        .status-cancelled {
            background: #fff5f5;
            color: #c53030;
            border: 2px solid #f56565;
        }

        .status-processing {
            background: #e0f2fe;
            color: #075985;
            border: 2px solid #38bdf8;
        }

        /* Order Details Grid */
        .order-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .detail-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }

        .detail-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .detail-value {
            font-size: 16px;
            color: #333;
            font-weight: 600;
        }

        .detail-value.amount {
            color: #667eea;
            font-size: 20px;
        }

        /* Order Items */
        .order-items-section {
            margin-top: 25px;
            padding-top: 25px;
            border-top: 2px solid #f0f0f0;
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .items-grid {
            display: grid;
            gap: 15px;
        }

        .item-card {
            display: flex;
            gap: 15px;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 12px;
            transition: all 0.3s;
        }

        .item-card:hover {
            background: #e9ecef;
        }

        .item-image {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid #e0e0e0;
        }

        .item-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .item-name {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .item-quantity {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }

        .item-colors {
            display: flex;
            gap: 6px;
            margin-top: 8px;
            flex-wrap: wrap;
            margin-bottom: 5px;
        }

        .item-color-circle {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 2px solid #ddd;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .item-price {
            font-size: 16px;
            font-weight: 700;
            color: #667eea;
        }

        /* Action Buttons */
        .order-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102,126,234,0.4);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-info:hover {
            background: #138496;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .order-header {
                flex-direction: column;
            }

            .order-details {
                grid-template-columns: 1fr;
            }

            .item-card {
                flex-direction: column;
                text-align: center;
            }

            .item-image {
                width: 100%;
                height: 150px;
            }
        }

        /* Transaction Info */
        .transaction-info {
            background: linear-gradient(135deg, #e0e7ff 0%, #f0f3ff 100%);
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
            border: 2px solid #c7d2fe;
        }

        .transaction-info p {
            margin: 5px 0;
            color: #4338ca;
            font-weight: 600;
        }

        .transaction-info strong {
            color: #312e81;
        }
    </style>
</head>
<body>

<?php UserLayout::navbar(); ?>
<?php UserLayout::sidebar(); ?>

<div class="main-content">
    <div class="container">
        <div class="page-header">
            <h1>📦 My Orders</h1>
            <p>Track and manage your orders</p>
        </div>

        <?php if ($success_message): ?>
            <div class="alert-success">
                ✓ <?= htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($orders)): ?>
            <!-- Empty State -->
            <div class="empty-state">
                <div class="empty-state-icon">📦</div>
                <h2>No Orders Yet</h2>
                <p>You haven't placed any orders yet. Start shopping to see your orders here!</p>
                <a href="shop.php" class="btn-shop">🛍️ Start Shopping</a>
            </div>
        <?php else: ?>
            <!-- Orders List -->
            <?php foreach ($orders as $order): ?>
                <?php 
                $order_items = getOrderItems($conn, $order['id']);
                $status_class = 'status-' . $order['status'];
                $status_text = ucfirst($order['status']);
                $status_icon = match($order['status']) {
                    'pending' => '⏳',
                    'completed' => '✅',
                    'cancelled' => '❌',
                    'processing' => '🔄',
                    default => '📋'
                };
                ?>
                
                <div class="order-card">
                    <!-- Order Header -->
                    <div class="order-header">
                        <div class="order-info">
                            <div class="order-id">Order #<?= $order['id']; ?></div>
                            <div class="order-date">
                                Placed on <?= date('F j, Y \a\t g:i A', strtotime($order['created_at'])); ?>
                            </div>
                        </div>
                        <div>
                            <span class="status-badge <?= $status_class; ?>">
                                <?= $status_icon; ?> <?= $status_text; ?>
                            </span>
                        </div>
                    </div>

                    <!-- Order Details Grid -->
                    <div class="order-details">
                        <div class="detail-item">
                            <div class="detail-label">💰 Total Amount</div>
                            <div class="detail-value amount">$<?= number_format($order['total_amount'], 2); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">💳 Payment Method</div>
                            <div class="detail-value"><?= ucfirst(str_replace('_', ' ', $order['payment_method'])); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">📞 Phone</div>
                            <div class="detail-value"><?= htmlspecialchars($order['phone']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">📍 Delivery Status</div>
                            <div class="detail-value">
                                <?php if ($order['status'] === 'completed'): ?>
                                    Ready to Ship
                                <?php elseif ($order['status'] === 'processing'): ?>
                                    Preparing
                                <?php elseif ($order['status'] === 'pending'): ?>
                                    Awaiting Verification
                                <?php else: ?>
                                    Not Available
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Delivery Address -->
                    <div class="detail-item" style="margin-top: 15px;">
                        <div class="detail-label">📍 Delivery Address</div>
                        <div class="detail-value" style="white-space: pre-line; font-size: 14px;">
                            <?= nl2br(htmlspecialchars($order['delivery_address'])); ?>
                        </div>
                    </div>

                    <!-- Transaction Info -->
                    <?php if ($order['transaction_id']): ?>
                    <div class="transaction-info">
                        <p><strong>🔖 Transaction ID:</strong> <?= htmlspecialchars($order['transaction_id']); ?></p>
                        <p><strong>📅 Payment Date:</strong> <?= date('F j, Y \a\t g:i A', strtotime($order['payment_date'])); ?></p>
                        <?php if ($order['payment_status']): ?>
                        <p><strong>💳 Payment Status:</strong> <?= ucfirst($order['payment_status']); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Order Items -->
                    <?php if (!empty($order_items)): ?>
                    <div class="order-items-section">
                        <h3 class="section-title">
                            <span>🛍️</span>
                            <span>Order Items (<?= count($order_items); ?>)</span>
                        </h3>
                        
                        <div class="items-grid">
                            <?php foreach ($order_items as $item): ?>
                            <div class="item-card">
                                <img src="../<?= htmlspecialchars($item['image_url']); ?>" 
                                     alt="<?= htmlspecialchars($item['name']); ?>" 
                                     class="item-image"
                                     onerror="this.src='../uploads/default-product.png'">
                                <div class="item-info">
                                    <div class="item-name"><?= htmlspecialchars($item['name']); ?></div>
                                    <div class="item-quantity">
                                        Quantity: <?= $item['quantity']; ?> × $<?= number_format($item['price'], 2); ?>
                                    </div>
                                    
                                    <!-- Display Colors if Available -->
                                    <?php if (!empty($item['colors_array']) && is_array($item['colors_array'])): ?>
                                        <div class="item-colors">
                                            <?php foreach ($item['colors_array'] as $color): ?>
                                                <div class="item-color-circle" 
                                                     style="background-color: <?= htmlspecialchars($color); ?>;"
                                                     title="<?= htmlspecialchars($color); ?>"></div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="item-price">
                                        Subtotal: $<?= number_format($item['subtotal'], 2); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Order Actions -->
                    <div class="order-actions">
                        <?php if ($order['status'] === 'pending'): ?>
                            <a href="wait-receipt-verification.php?order_id=<?= $order['id']; ?>&transaction_id=<?= urlencode($order['transaction_id']); ?>" 
                               class="btn btn-primary">
                                👁️ View Payment Status
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($order['receipt_image']): ?>
                            <a href="../<?= htmlspecialchars($order['receipt_image']); ?>" 
                               target="_blank" 
                               class="btn btn-info">
                                📄 View Receipt
                            </a>
                        <?php endif; ?>
                        
                        <a href="support.php" class="btn btn-secondary">
                            💬 Contact Support
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>
</div>

</body>
</html>