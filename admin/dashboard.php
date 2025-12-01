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

// Fetch dashboard statistics
try {
    // Total Users
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $total_users = $stmt->fetch()['total'];
    
    // Total Products
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products");
    $total_products = $stmt->fetch()['total'];
    
    // Total Orders
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders");
    $total_orders = $stmt->fetch()['total'];
    
    // Total Revenue
    $stmt = $pdo->query("SELECT SUM(total_amount) as revenue FROM orders WHERE status = 'completed'");
    $total_revenue = $stmt->fetch()['revenue'] ?? 0;
    
    // Order Statistics
    $stmt = $pdo->query("
        SELECT 
            status,
            COUNT(*) as count
        FROM orders
        GROUP BY status
    ");
    $order_stats = $stmt->fetchAll();
    
    // Recent Orders
    $stmt = $pdo->query("
        SELECT o.id, o.total_amount, o.status, o.created_at, u.username, u.email
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $recent_orders = $stmt->fetchAll();
    
    // Payment Status
    $stmt = $pdo->query("
        SELECT 
            status,
            COUNT(*) as count
        FROM payments
        GROUP BY status
    ");
    $payment_stats = $stmt->fetchAll();
    
    // Support Tickets
    $stmt = $pdo->query("
        SELECT 
            status,
            COUNT(*) as count
        FROM support_tickets
        GROUP BY status
    ");
    $ticket_stats = $stmt->fetchAll();
    
    // Low Stock Products
    $stmt = $pdo->query("
        SELECT id, name, stock, price
        FROM products
        WHERE stock <= 5 AND stock > 0
        ORDER BY stock ASC
        LIMIT 5
    ");
    $low_stock = $stmt->fetchAll();
    
    // Total Categories
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM categories");
    $total_categories = $stmt->fetch()['total'];
    
    // Monthly Revenue
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            SUM(total_amount) as revenue,
            COUNT(*) as orders
        FROM orders
        WHERE status = 'completed'
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
        LIMIT 6
    ");
    $monthly_revenue = $stmt->fetchAll();
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Count statistics for orders
$pending_orders = 0;
$processing_orders = 0;
$completed_orders = 0;
$cancelled_orders = 0;

foreach ($order_stats as $stat) {
    if ($stat['status'] == 'pending') $pending_orders = $stat['count'];
    elseif ($stat['status'] == 'processing') $processing_orders = $stat['count'];
    elseif ($stat['status'] == 'completed') $completed_orders = $stat['count'];
    elseif ($stat['status'] == 'cancelled') $cancelled_orders = $stat['count'];
}

// Count payment statistics
$pending_payments = 0;
$completed_payments = 0;
$failed_payments = 0;

foreach ($payment_stats as $stat) {
    if ($stat['status'] == 'pending') $pending_payments = $stat['count'];
    elseif ($stat['status'] == 'completed') $completed_payments = $stat['count'];
    elseif ($stat['status'] == 'failed') $failed_payments = $stat['count'];
}

// Count ticket statistics
$open_tickets = 0;
$pending_tickets = 0;
$closed_tickets = 0;

foreach ($ticket_stats as $stat) {
    if ($stat['status'] == 'open') $open_tickets = $stat['count'];
    elseif ($stat['status'] == 'pending') $pending_tickets = $stat['count'];
    elseif ($stat['status'] == 'closed') $closed_tickets = $stat['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../navbar_sidebar.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #fff;
        }

        .content {
            margin-left: 100px;
            width: calc(100% - 100px);
            padding: 40px 20px;
            margin-top: 60px;
            transition: margin-left 0.35s ease-in-out, width 0.35s ease-in-out;
        }

        .content.sidebar-expanded {
            margin-left: 230px;
            width: calc(100% - 230px);
        }

        .dashboard-container {
            max-width: 1600px;
            margin: 0 auto;
        }

        .page-header {
            margin-bottom: 40px;
        }

        .page-header h1 {
            font-size: 32px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .page-header p {
            color: #7f8c8d;
            font-size: 14px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
            transition: all 0.35s ease-in-out;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #667eea;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }

        .stat-card.users { border-left-color: #667eea; }
        .stat-card.products { border-left-color: #2ecc71; }
        .stat-card.orders { border-left-color: #f39c12; }
        .stat-card.revenue { border-left-color: #e74c3c; }
        .stat-card.categories { border-left-color: #9b59b6; }
        .stat-card.support { border-left-color: #1abc9c; }

        .stat-icon {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 12px;
            font-weight: 600;
            color: #95a5a6;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #2c3e50;
        }

        .stat-change {
            font-size: 12px;
            margin-top: 8px;
            color: #2ecc71;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 40px;
            transition: all 0.35s ease-in-out;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            padding: 25px;
            transition: all 0.35s ease-in-out;
        }

        .card-title {
            font-size: 18px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .order-status-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .status-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            text-align: center;
        }

        .status-item.pending {
            background: rgba(243, 156, 18, 0.1);
        }

        .status-item.processing {
            background: rgba(52, 152, 219, 0.1);
        }

        .status-item.completed {
            background: rgba(46, 204, 113, 0.1);
        }

        .status-item.cancelled {
            background: rgba(231, 76, 60, 0.1);
        }

        .status-label {
            font-size: 12px;
            font-weight: 600;
            color: #7f8c8d;
            text-transform: uppercase;
        }

        .status-count {
            font-size: 24px;
            font-weight: 700;
            margin-top: 5px;
        }

        .status-item.pending .status-count { color: #f39c12; }
        .status-item.processing .status-count { color: #3498db; }
        .status-item.completed .status-count { color: #2ecc71; }
        .status-item.cancelled .status-count { color: #e74c3c; }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table thead {
            background: #f8f9fa;
        }

        table th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #e0e0e0;
            font-size: 12px;
            text-transform: uppercase;
        }

        table td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
            color: #555;
            font-size: 13px;
        }

        table tbody tr:hover {
            background: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-pending { background: rgba(243, 156, 18, 0.2); color: #f39c12; }
        .badge-processing { background: rgba(52, 152, 219, 0.2); color: #3498db; }
        .badge-completed { background: rgba(46, 204, 113, 0.2); color: #2ecc71; }
        .badge-cancelled { background: rgba(231, 76, 60, 0.2); color: #e74c3c; }

        .btn {
            display: inline-block;
            padding: 8px 16px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
        }

        .btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c8d;
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .low-stock-warning {
            padding: 10px;
            background: rgba(231, 76, 60, 0.1);
            border-left: 3px solid #e74c3c;
            border-radius: 4px;
            color: #e74c3c;
            font-size: 12px;
            margin-bottom: 8px;
        }

        @media (max-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                width: 100%;
                padding: 20px 10px;
                margin-top: 50px;
            }

            .content.sidebar-expanded {
                margin-left: 200px;
                width: calc(100% - 200px);
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 12px;
            }

            .stat-number {
                font-size: 24px;
            }

            .order-status-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<?php Layout::navbar(); ?>
<?php Layout::sidebar(); ?>

<div class="content">
    <div class="dashboard-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>📊 Admin Dashboard</h1>
            <p>Welcome back! Here's your business overview</p>
        </div>

        <!-- Key Statistics -->
        <div class="stats-grid">
            <div class="stat-card users">
                <div class="stat-icon">👥</div>
                <div class="stat-label">Total Users</div>
                <div class="stat-number"><?php echo $total_users; ?></div>
                <div class="stat-change">↑ Active members</div>
            </div>

            <div class="stat-card products">
                <div class="stat-icon">📦</div>
                <div class="stat-label">Total Products</div>
                <div class="stat-number"><?php echo $total_products; ?></div>
                <div class="stat-change"><?php echo $total_categories; ?> categories</div>
            </div>

            <div class="stat-card orders">
                <div class="stat-icon">📋</div>
                <div class="stat-label">Total Orders</div>
                <div class="stat-number"><?php echo $total_orders; ?></div>
                <div class="stat-change"><?php echo $pending_orders; ?> pending</div>
            </div>

            <div class="stat-card revenue">
                <div class="stat-icon">💰</div>
                <div class="stat-label">Total Revenue</div>
                <div class="stat-number">$<?php echo number_format($total_revenue, 2); ?></div>
                <div class="stat-change">Completed orders</div>
            </div>

            <div class="stat-card categories">
                <div class="stat-icon">🏷️</div>
                <div class="stat-label">Categories</div>
                <div class="stat-number"><?php echo $total_categories; ?></div>
                <div class="stat-change">Product categories</div>
            </div>

            <div class="stat-card support">
                <div class="stat-icon">💬</div>
                <div class="stat-label">Support Tickets</div>
                <div class="stat-number"><?php echo $open_tickets + $pending_tickets; ?></div>
                <div class="stat-change"><?php echo $open_tickets; ?> open</div>
            </div>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Order Status -->
            <div class="card">
                <div class="card-title">📊 Order Status Overview</div>
                <div class="order-status-grid">
                    <div class="status-item pending">
                        <div class="status-label">Pending</div>
                        <div class="status-count"><?php echo $pending_orders; ?></div>
                    </div>
                    <div class="status-item processing">
                        <div class="status-label">Processing</div>
                        <div class="status-count"><?php echo $processing_orders; ?></div>
                    </div>
                    <div class="status-item completed">
                        <div class="status-label">Completed</div>
                        <div class="status-count"><?php echo $completed_orders; ?></div>
                    </div>
                    <div class="status-item cancelled">
                        <div class="status-label">Cancelled</div>
                        <div class="status-count"><?php echo $cancelled_orders; ?></div>
                    </div>
                </div>
            </div>

            <!-- Payment Status -->
            <div class="card">
                <div class="card-title">💳 Payment Status</div>
                <div class="order-status-grid">
                    <div class="status-item pending">
                        <div class="status-label">Pending</div>
                        <div class="status-count"><?php echo $pending_payments; ?></div>
                    </div>
                    <div class="status-item completed">
                        <div class="status-label">Completed</div>
                        <div class="status-count"><?php echo $completed_payments; ?></div>
                    </div>
                    <div class="status-item cancelled">
                        <div class="status-label">Failed</div>
                        <div class="status-count"><?php echo $failed_payments; ?></div>
                    </div>
                    <div class="status-item processing">
                        <div class="status-label">Support Tickets</div>
                        <div class="status-count"><?php echo $pending_tickets; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-title">📦 Recent Orders</div>
            <div class="table-container">
                <?php if (!empty($recent_orders)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td>#<?php echo $order['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($order['username']); ?></strong><br>
                                        <small style="color: #999;"><?php echo htmlspecialchars($order['email']); ?></small>
                                    </td>
                                    <td><strong>$<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                    <td>
                                        <span class="badge badge-<?php echo $order['status']; ?>">
                                            <?php echo $order['status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                    <td>
                                        <a href="view_orders.php?page=1" class="btn">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📭</div>
                        <p>No orders yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Low Stock Products -->
        <div class="card" style="margin-bottom: 20px;">
            <div class="card-title">⚠️ Low Stock Products</div>
            <div class="table-container">
                <?php if (!empty($low_stock)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Current Stock</th>
                                <th>Price</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($low_stock as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td>
                                        <div class="low-stock-warning">
                                            <?php echo $product['stock']; ?> items
                                        </div>
                                    </td>
                                    <td>$<?php echo number_format($product['price'], 2); ?></td>
                                    <td><a href="products.php" class="btn">Manage</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">✅</div>
                        <p>All products have sufficient stock</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="dashboard-grid" style="margin-bottom: 20px;">
            <div class="card">
                <div class="card-title">🔗 Quick Management</div>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <a href="view_orders.php" class="btn" style="text-align: center;">View All Orders</a>
                    <a href="categories.php" class="btn" style="text-align: center; background: #2ecc71;">Manage Categories</a>
                    <a href="products.php" class="btn" style="text-align: center; background: #9b59b6;">Manage Products</a>
                </div>
            </div>

            <div class="card">
                <div class="card-title">📞 Support Management</div>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <a href="Customer_Support.php" class="btn" style="text-align: center; background: #1abc9c;">View Support Tickets</a>
                    <a href="Customer_Support.php?status=open" class="btn" style="text-align: center; background: #f39c12;">Open Tickets (<?php echo $open_tickets; ?>)</a>
                    <a href="Customer_Support.php?status=pending" class="btn" style="text-align: center; background: #e67e22;">Pending Tickets (<?php echo $pending_tickets; ?>)</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Detect sidebar hover/expansion and adjust content
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar');
    const content = document.querySelector('.content');

    if (sidebar && content) {
        sidebar.addEventListener('mouseenter', function() {
            content.classList.add('sidebar-expanded');
        });
        
        sidebar.addEventListener('mouseleave', function() {
            content.classList.remove('sidebar-expanded');
        });
    }
});
</script>

</body>
</html>