<?php
session_start();

// Include Layout class
include "../classes/Layout.php";

// Protect page: only admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Database connection
require_once '../config/database.php';
$database = new Database();
$pdo = $database->getConnection();

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error_message = '';
$order = null;
$order_items = [];
$payment_info = null;

// Fetch order details
if ($order_id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                o.id,
                o.user_id,
                o.total_amount,
                o.status,
                o.payment_method,
                o.phone,
                o.delivery_address,
                o.created_at,
                o.updated_at,
                u.username,
                u.email
            FROM orders o
            JOIN users u ON o.user_id = u.id
            WHERE o.id = ?
        ");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();
        
        if (!$order) {
            $error_message = "Order not found.";
        }
    } catch (PDOException $e) {
        $error_message = "Error fetching order: " . $e->getMessage();
    }
}

// Fetch order items if order exists (NOW INCLUDING COLORS)
if ($order) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                oi.id,
                oi.product_id,
                oi.quantity,
                oi.price,
                p.name,
                p.image_url,
                p.colors,
                p.sizes,
                p.materials,
                p.brief_details,
                c.name as category
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$order_id]);
        $order_items = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error_message = "Error fetching order items: " . $e->getMessage();
    }
    
    // Fetch payment information
    try {
        $stmt = $pdo->prepare("
            SELECT 
                id,
                payment_method,
                amount,
                transaction_id,
                status,
                created_at,
                receipt_image
            FROM payments
            WHERE order_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$order_id]);
        $payment_info = $stmt->fetch();
    } catch (PDOException $e) {
        $error_message = "Error fetching payment info: " . $e->getMessage();
    }
}

// Handle status update
$update_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    try {
        $new_status = $_POST['status'];
        $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_status, $order_id]);
        $update_message = "Order status updated successfully!";
        
        // Refresh order data
        $stmt = $pdo->prepare("
            SELECT 
                o.id, o.user_id, o.total_amount, o.status, o.payment_method,
                o.phone, o.delivery_address, o.created_at, o.updated_at,
                u.username, u.email
            FROM orders o
            JOIN users u ON o.user_id = u.id
            WHERE o.id = ?
        ");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();
    } catch (PDOException $e) {
        $error_message = "Error updating status: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - Admin</title>
    <link rel="stylesheet" href="../navbar_sidebar.css">
    <style>
        .content {
            margin-left: 100px;
            width: 90%;
            padding: 20px;
            margin-top: 60px;
        }

        .order-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .header-section h1 {
            margin: 0;
            color: #333;
        }

        .back-btn {
            padding: 10px 20px;
            background-color: #6c757d;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            transition: background-color 0.3s;
        }

        .back-btn:hover {
            background-color: #5a6268;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .order-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .card-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 14px;
            color: #333;
            font-weight: 500;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            width: fit-content;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-processing {
            background-color: #cfe2ff;
            color: #084298;
        }

        .status-completed {
            background-color: #d1e7dd;
            color: #0f5132;
        }

        .status-cancelled {
            background-color: #f8d7da;
            color: #842029;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .items-table thead {
            background-color: #f8f9fa;
        }

        .items-table th {
            padding: 12px;
            text-align: left;
            font-weight: bold;
            color: #333;
            border-bottom: 2px solid #dee2e6;
            font-size: 13px;
        }

        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
            font-size: 14px;
        }

        .items-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .product-cell {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .product-image {
            width: 60px;
            height: 60px;
            object-fit: contain;
            border-radius: 4px;
            background: #f8f9fa;
            padding: 4px;
        }

        .product-info {
            flex: 1;
        }

        .product-info h4 {
            margin: 0 0 3px 0;
            color: #333;
            font-size: 14px;
        }

        .product-info p {
            margin: 3px 0;
            color: #999;
            font-size: 12px;
        }

        .product-colors {
            display: flex;
            gap: 6px;
            margin-top: 8px;
            flex-wrap: wrap;
        }

        .color-dot {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid #ddd;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            cursor: pointer;
        }

        .details-section {
            background: #f8f9fa;
            padding: 10px 12px;
            border-radius: 4px;
            margin-top: 10px;
            font-size: 12px;
        }

        .details-section strong {
            display: block;
            color: #333;
            margin-bottom: 4px;
        }

        .details-section p {
            margin: 3px 0;
            color: #666;
        }

        .total-section {
            text-align: right;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 2px solid #f0f0f0;
        }

        .total-row {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 8px;
            gap: 30px;
        }

        .total-label {
            font-weight: 600;
            color: #333;
            min-width: 100px;
        }

        .total-value {
            font-weight: 600;
            color: #333;
            min-width: 120px;
            text-align: right;
        }

        .grand-total {
            font-size: 18px;
            color: #28a745;
            margin-top: 15px;
        }

        .payment-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-top: 15px;
        }

        .receipt-image {
            max-width: 200px;
            margin-top: 10px;
            border-radius: 4px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s;
        }

        .btn-primary {
            background-color: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background-color: #0056b3;
        }

        .btn-success {
            background-color: #28a745;
            color: white;
        }

        .btn-success:hover {
            background-color: #218838;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .modal-header h2 {
            margin: 0 0 20px 0;
            color: #333;
        }

        .close {
            float: right;
            font-size: 28px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
        }

        .close:hover {
            color: #000;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
            font-size: 14px;
        }

        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 10px;
            }

            .header-section {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .items-table {
                font-size: 12px;
            }

            .product-image {
                width: 50px;
                height: 50px;
            }
        }
    </style>
</head>
<body>

<?php Layout::navbar(); ?>
<?php Layout::sidebar(); ?>

<div class="content">
    <div class="order-container">
        <div class="header-section">
            <h1>Order Details</h1>
            <a href="view_orders.php" class="back-btn">← Back to Orders</a>
        </div>

        <?php if ($update_message): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($update_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($order): ?>
            <!-- Order Header -->
            <div class="order-card">
                <div class="card-title">Order #<?php echo htmlspecialchars($order['id']); ?></div>
                
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Order Status</span>
                        <span class="status-badge status-<?php echo htmlspecialchars($order['status']); ?>">
                            <?php echo htmlspecialchars($order['status']); ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Order Date</span>
                        <span class="info-value"><?php echo date('M d, Y H:i A', strtotime($order['created_at'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Last Updated</span>
                        <span class="info-value"><?php echo date('M d, Y H:i A', strtotime($order['updated_at'])); ?></span>
                    </div>
                </div>
            </div>

            <!-- Customer Information -->
            <div class="order-card">
                <div class="card-title">Customer Information</div>
                
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Customer Name</span>
                        <span class="info-value"><?php echo htmlspecialchars($order['username']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email</span>
                        <span class="info-value"><?php echo htmlspecialchars($order['email']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Phone</span>
                        <span class="info-value"><?php echo htmlspecialchars($order['phone']); ?></span>
                    </div>
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Delivery Address</span>
                        <span class="info-value"><?php echo htmlspecialchars($order['delivery_address']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Payment Method</span>
                        <span class="info-value"><?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($order['payment_method']))); ?></span>
                    </div>
                </div>
            </div>

            <!-- Order Items (NOW WITH COLORS AND FULL DETAILS) -->
            <div class="order-card">
                <div class="card-title">Order Items</div>
                
                <?php if (!empty($order_items)): ?>
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order_items as $item): 
                                // Decode product colors
                                $colors = [];
                                if (!empty($item['colors']) && $item['colors'] !== '[]') {
                                    $decoded = json_decode($item['colors'], true);
                                    if (is_array($decoded)) {
                                        $colors = $decoded;
                                    }
                                }
                            ?>
                                <tr>
                                    <td>
                                        <div class="product-cell">
                                            <?php if ($item['image_url']): ?>
                                                <img src="../<?php echo htmlspecialchars($item['image_url']); ?>" 
                                                     alt="Product" class="product-image" onerror="this.src='../placeholder.png'">
                                            <?php else: ?>
                                                <img src="../placeholder.png" alt="No Image" class="product-image">
                                            <?php endif; ?>
                                            <div class="product-info">
                                                <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                                                <p><?php echo htmlspecialchars($item['category'] ?? 'N/A'); ?></p>
                                                <p style="font-size: 11px; color: #999;">ID: <?php echo htmlspecialchars($item['product_id']); ?></p>
                                                
                                                <!-- Show Colors -->
                                                <?php if (!empty($colors)): ?>
                                                    <div class="product-colors">
                                                        <?php foreach ($colors as $color): ?>
                                                            <div class="color-dot" 
                                                                 style="background-color: <?php echo htmlspecialchars($color); ?>;"
                                                                 title="<?php echo htmlspecialchars($color); ?>"></div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <!-- Show Sizes, Materials, Details -->
                                                <?php if (!empty($item['sizes']) || !empty($item['materials']) || !empty($item['brief_details'])): ?>
                                                    <div class="details-section">
                                                        <?php if (!empty($item['sizes'])): ?>
                                                            <p><strong style="font-size: 11px;">📏 Sizes:</strong> <?php echo htmlspecialchars($item['sizes']); ?></p>
                                                        <?php endif; ?>
                                                        <?php if (!empty($item['materials'])): ?>
                                                            <p><strong style="font-size: 11px;">🧵 Materials:</strong> <?php echo htmlspecialchars($item['materials']); ?></p>
                                                        <?php endif; ?>
                                                        <?php if (!empty($item['brief_details'])): ?>
                                                            <p><strong style="font-size: 11px;">✨ Details:</strong> <?php echo htmlspecialchars($item['brief_details']); ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                    <td>$<?php echo number_format($item['price'], 2); ?></td>
                                    <td><strong>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="total-section">
                        <div class="total-row">
                            <span class="total-label">Total Amount:</span>
                            <span class="total-value" style="color: #28a745; font-size: 16px;">
                                $<?php echo number_format($order['total_amount'], 2); ?>
                            </span>
                        </div>
                    </div>
                <?php else: ?>
                    <p style="color: #999;">No items found for this order.</p>
                <?php endif; ?>
            </div>

            <!-- Payment Information -->
            <?php if ($payment_info): ?>
                <div class="order-card">
                    <div class="card-title">Payment Information</div>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Transaction ID</span>
                            <span class="info-value"><?php echo htmlspecialchars($payment_info['transaction_id']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Payment Method</span>
                            <span class="info-value"><?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($payment_info['payment_method']))); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Amount</span>
                            <span class="info-value">$<?php echo number_format($payment_info['amount'], 2); ?></span>
                        </div>
                    </div>

                    <div class="payment-section">
                        <strong>Status:</strong> 
                        <span class="status-badge status-<?php echo htmlspecialchars($payment_info['status']); ?>" 
                              style="margin-left: 10px;">
                            <?php echo htmlspecialchars($payment_info['status']); ?>
                        </span>
                        <p style="color: #999; font-size: 12px; margin-top: 5px;">
                            <?php echo date('M d, Y H:i A', strtotime($payment_info['created_at'])); ?>
                        </p>

                        <?php if ($payment_info['receipt_image']): ?>
                            <div>
                                <strong style="display: block; margin-top: 15px;">Receipt:</strong>
                                <img src="../<?php echo htmlspecialchars($payment_info['receipt_image']); ?>" 
                                     alt="Receipt" class="receipt-image" 
                                     onerror="this.style.display='none'">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="order-card">
                <div class="action-buttons">
                    <button onclick="openStatusModal()" class="btn btn-primary">Update Status</button>
                    <a href="view_orders.php" class="btn btn-success">Back to Orders</a>
                </div>
            </div>

        <?php else: ?>
            <div class="order-card">
                <p style="color: #999; text-align: center;">Order not found. <a href="view_orders.php">Go back to orders</a></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Status Update Modal -->
<?php if ($order): ?>
<div id="statusModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeStatusModal()">&times;</span>
        <div class="modal-header">
            <h2>Update Order Status</h2>
        </div>
        <form method="POST" action="">
            <div class="form-group">
                <label for="modal_status">New Status</label>
                <select id="modal_status" name="status" required>
                    <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                    <option value="completed" <?php echo $order['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="modal-buttons">
                <button type="submit" name="update_status" class="btn btn-primary">Update</button>
                <button type="button" onclick="closeStatusModal()" class="btn btn-success">Cancel</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function openStatusModal() {
    document.getElementById('statusModal').style.display = 'block';
}

function closeStatusModal() {
    document.getElementById('statusModal').style.display = 'none';
}

window.onclick = function(event) {
    var modal = document.getElementById('statusModal');
    if (modal && event.target == modal) {
        modal.style.display = 'none';
    }
}
</script>

</body>
</html>