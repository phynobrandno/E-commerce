<?php
// user/track_orders.php
session_start();
require_once __DIR__ . '/../classes/UserLayout.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$host = 'localhost';
$dbname = 'user_system';
$username = 'root';
$password = '';

try {
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'];

// Get user's orders with tracking - Only orders with confirmed payments
$orders_query = $conn->prepare("
    SELECT o.id, o.total_amount, o.status, o.shipment_status, 
           o.tracking_number, o.carrier, o.estimated_delivery, 
           o.created_at, o.updated_at,
           COUNT(oi.id) as item_count,
           p.status as payment_status, p.transaction_id
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN payments p ON o.id = p.order_id
    WHERE o.user_id = ? AND o.status = 'completed' AND p.status = 'completed'
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$orders_query->bind_param("i", $user_id);
$orders_query->execute();
$orders_result = $orders_query->get_result();
$orders = $orders_result->fetch_all(MYSQLI_ASSOC);
$orders_query->close();

// Get tracking history for specific order
function getTrackingHistory($conn, $order_id) {
    $stmt = $conn->prepare("
        SELECT * FROM order_tracking_history 
        WHERE order_id = ? 
        ORDER BY updated_at DESC
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $history = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $history;
}

// Get order items with colors
function getOrderItems($conn, $order_id) {
    $stmt = $conn->prepare("
        SELECT oi.quantity, oi.price, p.name, p.image_url, p.colors,
               (oi.quantity * oi.price) as subtotal
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
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
    $stmt->close();
    return $items;
}

$selected_order = null;
if (isset($_GET['order_id'])) {
    $order_id = (int)$_GET['order_id'];
    foreach ($orders as $order) {
        if ($order['id'] === $order_id) {
            $selected_order = $order;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Your Orders</title>
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
            margin-left: 100px;
            width: calc(100% - 100px);
            padding: 40px 20px;
            margin-top: 60px;
            transition: margin-left 0.35s ease-in-out, width 0.35s ease-in-out;
        }

        .main-content.sidebar-expanded {
            margin-left: 230px;
            width: calc(100% - 230px);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
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
            margin-bottom: 10px;
        }

        .page-header p {
            color: #666;
            font-size: 16px;
        }

        .tracking-layout {
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 30px;
        }

        /* Orders List */
        .orders-sidebar {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 25px;
            height: fit-content;
            transition: all 0.35s ease-in-out;
        }

        .sidebar-title {
            font-size: 18px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .orders-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-height: 600px;
            overflow-y: auto;
        }

        .order-item {
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            background: #f9f9f9;
        }

        .order-item:hover {
            border-color: #667eea;
            background: #f0f2ff;
            transform: translateX(5px);
        }

        .order-item.active {
            border-color: #667eea;
            background: #e0e7ff;
            box-shadow: 0 4px 12px rgba(102,126,234,0.3);
        }

        .order-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .order-item-id {
            font-weight: 700;
            color: #2c3e50;
        }

        .order-item-status {
            font-size: 11px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 12px;
            text-transform: uppercase;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-processed { background: #d1ecf1; color: #0c5460; }
        .status-shipped { background: #cce5ff; color: #004085; }
        .status-in_transit { background: #e2e3e5; color: #383d41; }
        .status-out_for_delivery { background: #fff3cd; color: #856404; }
        .status-delivered { background: #d4edda; color: #155724; }

        .order-item-date {
            font-size: 12px;
            color: #999;
        }

        .empty-orders {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }

        .empty-orders-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        /* Tracking Details */
        .tracking-details {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 30px;
            transition: all 0.35s ease-in-out;
        }

        .empty-state {
            text-align: center;
            padding: 80px 40px;
            color: #999;
        }

        .empty-state-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }

        .empty-state h2 {
            font-size: 24px;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .tracking-header {
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .tracking-title {
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .tracking-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }

        .meta-item {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            border-left: 3px solid #667eea;
        }

        .meta-label {
            font-size: 11px;
            color: #999;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .meta-value {
            font-size: 16px;
            font-weight: 700;
            color: #2c3e50;
        }

        /* Timeline */
        .timeline-section {
            margin: 30px 0;
        }

        .timeline-title {
            font-size: 18px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 20px;
        }

        .timeline {
            position: relative;
            padding: 20px 0;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 12px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(to bottom, #667eea, #764ba2);
        }

        .timeline-item {
            margin-bottom: 25px;
            padding-left: 50px;
            position: relative;
        }

        .timeline-dot {
            position: absolute;
            left: 0;
            top: 5px;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: white;
            border: 3px solid #667eea;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .timeline-item.completed .timeline-dot {
            background: #667eea;
            color: white;
        }

        .timeline-item.pending .timeline-dot {
            background: white;
            border-color: #ddd;
        }

        .timeline-content {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 3px solid #667eea;
        }

        .timeline-item.pending .timeline-content {
            border-left-color: #ddd;
        }

        .timeline-status {
            font-size: 16px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .timeline-location {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
        }

        .timeline-description {
            font-size: 13px;
            color: #999;
            font-style: italic;
        }

        .timeline-date {
            font-size: 12px;
            color: #999;
            margin-top: 8px;
        }

        /* Items List */
        .items-section {
            margin: 30px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .items-title {
            font-size: 16px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
        }

        .item-card {
            background: white;
            border-radius: 8px;
            padding: 12px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .item-image {
            width: 100%;
            height: 100px;
            object-fit: cover;
            border-radius: 6px;
            margin-bottom: 10px;
        }

        .item-name {
            font-size: 13px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .item-quantity {
            font-size: 12px;
            color: #999;
            margin-bottom: 8px;
        }

        .item-colors {
            display: flex;
            gap: 4px;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 5px;
        }

        .item-color-circle {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            border: 1px solid #ddd;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        @media (max-width: 1024px) {
            .tracking-layout {
                grid-template-columns: 1fr;
            }

            .orders-sidebar {
                max-height: none;
            }

            .orders-list {
                max-height: none;
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }

            .order-item {
                text-align: center;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                width: 100%;
                margin-top: 50px;
            }

            .main-content.sidebar-expanded {
                margin-left: 200px;
                width: calc(100% - 200px);
            }

            .tracking-layout {
                grid-template-columns: 1fr;
            }

            .page-header h1 {
                font-size: 24px;
            }

            .tracking-meta {
                grid-template-columns: 1fr;
            }

            .items-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }
        }

        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: #d0d0d0;
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #999;
        }
    </style>
</head>
<body>

<?php UserLayout::navbar(); ?>
<?php UserLayout::sidebar(); ?>

<div class="main-content">
    <div class="container">
        <div class="page-header">
            <h1>🚚 Track Your Orders</h1>
            <p>Real-time tracking for your shipments</p>
        </div>

        <div class="tracking-layout">
            <!-- Orders Sidebar -->
            <div class="orders-sidebar">
                <div class="sidebar-title">
                    <span>📦</span> Your Orders
                </div>
                
                <div class="orders-list">
                    <?php if (empty($orders)): ?>
                        <div class="empty-orders">
                            <div class="empty-orders-icon">📭</div>
                            <p>No completed orders to track</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <div class="order-item <?= ($selected_order && $selected_order['id'] === $order['id']) ? 'active' : ''; ?>"
                                 onclick="window.location.href='?order_id=<?= $order['id']; ?>'">
                                <div class="order-item-header">
                                    <span class="order-item-id">#<?= $order['id']; ?></span>
                                    <span class="order-item-status status-<?= $order['shipment_status']; ?>">
                                        <?= str_replace('_', ' ', $order['shipment_status']); ?>
                                    </span>
                                </div>
                                <div class="order-item-date">
                                    <?= date('M d, Y', strtotime($order['created_at'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tracking Details -->
            <div class="tracking-details">
                <?php if ($selected_order): ?>
                    <?php $tracking_history = getTrackingHistory($conn, $selected_order['id']); ?>
                    <?php $order_items = getOrderItems($conn, $selected_order['id']); ?>

                    <div class="tracking-header">
                        <div class="tracking-title">Order #<?= $selected_order['id']; ?></div>
                        <div class="tracking-meta">
                            <div class="meta-item">
                                <div class="meta-label">Total Amount</div>
                                <div class="meta-value">$<?= number_format($selected_order['total_amount'], 2); ?></div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Status</div>
                                <div class="meta-value"><?= ucfirst(str_replace('_', ' ', $selected_order['shipment_status'])); ?></div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Carrier</div>
                                <div class="meta-value"><?= $selected_order['carrier'] ?? 'TBD'; ?></div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Est. Delivery</div>
                                <div class="meta-value"><?= $selected_order['estimated_delivery'] ? date('M d', strtotime($selected_order['estimated_delivery'])) : 'TBD'; ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Tracking Number -->
                    <?php if ($selected_order['tracking_number']): ?>
                    <div class="meta-item" style="margin-bottom: 25px;">
                        <div class="meta-label">Tracking Number</div>
                        <div class="meta-value"><?= htmlspecialchars($selected_order['tracking_number']); ?></div>
                    </div>
                    <?php endif; ?>

                    <!-- Timeline -->
                    <?php if (!empty($tracking_history)): ?>
                    <div class="timeline-section">
                        <div class="timeline-title">📍 Shipment Timeline</div>
                        <div class="timeline">
                            <?php foreach ($tracking_history as $index => $event): ?>
                            <div class="timeline-item <?= ($index === 0) ? 'completed' : 'pending'; ?>">
                                <div class="timeline-dot">
                                    <?php if ($index === 0): ?>✓<?php else: ?>•<?php endif; ?>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-status"><?= ucfirst(str_replace('_', ' ', $event['status'])); ?></div>
                                    <?php if ($event['location']): ?>
                                    <div class="timeline-location">📍 <?= htmlspecialchars($event['location']); ?></div>
                                    <?php endif; ?>
                                    <?php if ($event['description']): ?>
                                    <div class="timeline-description"><?= htmlspecialchars($event['description']); ?></div>
                                    <?php endif; ?>
                                    <div class="timeline-date"><?= date('M d, Y \a\t g:i A', strtotime($event['updated_at'])); ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Items -->
                    <?php if (!empty($order_items)): ?>
                    <div class="items-section">
                        <div class="items-title">🛍️ Items in This Order</div>
                        <div class="items-grid">
                            <?php foreach ($order_items as $item): ?>
                            <div class="item-card">
                                <img src="../<?= htmlspecialchars($item['image_url']); ?>" 
                                     alt="<?= htmlspecialchars($item['name']); ?>"
                                     class="item-image"
                                     onerror="this.src='../uploads/default-product.png'">
                                <div class="item-name"><?= htmlspecialchars($item['name']); ?></div>
                                <div class="item-quantity">Qty: <?= $item['quantity']; ?></div>
                                
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
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- Empty State -->
                    <div class="empty-state">
                        <div class="empty-state-icon">📦</div>
                        <h2>Select an Order</h2>
                        <p>Choose an order from the list to view tracking details</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Detect sidebar hover
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');

        if (sidebar && mainContent) {
            sidebar.addEventListener('mouseenter', function() {
                mainContent.classList.add('sidebar-expanded');
            });
            
            sidebar.addEventListener('mouseleave', function() {
                mainContent.classList.remove('sidebar-expanded');
            });
        }
    });
</script>

</body>
</html>