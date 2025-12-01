<?php
session_start();

// Include Layout and Database classes
include "../classes/Layout.php";
include "../config/database.php";

// Protect page: only admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$database = new Database();
$conn = $database->getConnection();

// Fetch analytics data
$stats = [];

// Total Users
$query = "SELECT COUNT(*) as total FROM users";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_users'] = $stmt->fetch()['total'];

// Total Orders
$query = "SELECT COUNT(*) as total FROM orders";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_orders'] = $stmt->fetch()['total'];

// Total Revenue
$query = "SELECT SUM(total_amount) as total FROM orders WHERE status IN ('completed', 'processing')";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_revenue'] = $stmt->fetch()['total'] ?? 0;

// Pending Orders
$query = "SELECT COUNT(*) as total FROM orders WHERE status = 'pending'";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['pending_orders'] = $stmt->fetch()['total'];

// Total Products
$query = "SELECT COUNT(*) as total FROM products";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['total_products'] = $stmt->fetch()['total'];

// Low Stock Products
$query = "SELECT COUNT(*) as total FROM products WHERE stock < 5";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['low_stock'] = $stmt->fetch()['total'];

// Orders by Status
$query = "SELECT status, COUNT(*) as count FROM orders GROUP BY status";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['orders_by_status'] = $stmt->fetchAll();

// Top Products
$query = "SELECT p.id, p.name, SUM(oi.quantity) as total_sold, p.price 
          FROM products p 
          LEFT JOIN order_items oi ON p.id = oi.product_id 
          GROUP BY p.id 
          ORDER BY total_sold DESC 
          LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['top_products'] = $stmt->fetchAll();

// Revenue by Payment Method
$query = "SELECT payment_method, SUM(amount) as total, COUNT(*) as count 
          FROM payments 
          WHERE status = 'completed' 
          GROUP BY payment_method";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['payment_methods'] = $stmt->fetchAll();

// Recent Orders
$query = "SELECT o.id, o.user_id, u.email, o.total_amount, o.status, o.created_at 
          FROM orders o 
          JOIN users u ON o.user_id = u.id 
          ORDER BY o.created_at DESC 
          LIMIT 10";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['recent_orders'] = $stmt->fetchAll();

// Open Support Tickets
$query = "SELECT COUNT(*) as total FROM support_tickets WHERE status != 'closed'";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['open_tickets'] = $stmt->fetch()['total'];

// Average Order Value
$query = "SELECT AVG(total_amount) as average FROM orders WHERE status IN ('completed', 'processing')";
$stmt = $conn->prepare($query);
$stmt->execute();
$stats['avg_order_value'] = $stmt->fetch()['average'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard</title>
    <link rel="stylesheet" href="../navbar_sidebar.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .analytics-container {
            padding: 40px;
            background: #ffffff;
            min-height: 100vh;
            margin: 0 auto;
            width: 100%;
        }

        .page-title {
            color: #333;
            margin-bottom: 30px;
            font-size: 28px;
            font-weight: 600;
            text-align: center;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            border-left: 5px solid #667eea;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
        }

        .stat-label {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
            text-transform: uppercase;
            font-weight: 600;
        }

        .stat-value {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
        }

        .stat-icon {
            font-size: 24px;
            margin-bottom: 10px;
        }

        .stat-change {
            font-size: 12px;
            color: #27ae60;
            margin-top: 5px;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .chart-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
        }

        .table-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .table-container table {
            width: 100%;
            border-collapse: collapse;
        }

        .table-container th {
            background: #f5f5f5;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #ddd;
        }

        .table-container td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            color: #666;
        }

        .table-container tr:hover {
            background: #f9f9f9;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-processing {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .currency {
            color: #667eea;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>

<?php Layout::navbar(); ?>
<?php Layout::sidebar(); ?>

<div class="main-content">
    <div class="analytics-container">
        <h1 class="page-title">📊 Analytics Dashboard</h1>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-label">Total Users</div>
                <div class="stat-value"><?php echo $stats['total_users']; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-label">Total Orders</div>
                <div class="stat-value"><?php echo $stats['total_orders']; ?></div>
                <div class="stat-change">Pending: <?php echo $stats['pending_orders']; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-label">Total Revenue</div>
                <div class="stat-value currency">$<?php echo number_format($stats['total_revenue'], 2); ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">🏆</div>
                <div class="stat-label">Avg Order Value</div>
                <div class="stat-value currency">$<?php echo number_format($stats['avg_order_value'], 2); ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-label">Total Products</div>
                <div class="stat-value"><?php echo $stats['total_products']; ?></div>
                <div class="stat-change">Low Stock: <?php echo $stats['low_stock']; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">💬</div>
                <div class="stat-label">Support Tickets</div>
                <div class="stat-value"><?php echo $stats['open_tickets']; ?></div>
                <div class="stat-change">Open Issues</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-container">
                <div class="chart-title">Orders by Status</div>
                <canvas id="statusChart"></canvas>
            </div>

            <div class="chart-container">
                <div class="chart-title">Revenue by Payment Method</div>
                <canvas id="paymentChart"></canvas>
            </div>
        </div>

        <!-- Top Products Table -->
        <div class="table-container">
            <div class="chart-title">🔥 Top 5 Best Selling Products</div>
            <table>
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Price</th>
                        <th>Units Sold</th>
                        <th>Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($stats['top_products'] as $product): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td class="currency">$<?php echo number_format($product['price'], 2); ?></td>
                        <td><?php echo $product['total_sold'] ?? 0; ?></td>
                        <td class="currency">$<?php echo number_format(($product['total_sold'] ?? 0) * $product['price'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Recent Orders Table -->
        <div class="table-container">
            <div class="chart-title">📋 Recent Orders</div>
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer Email</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($stats['recent_orders'] as $order): ?>
                    <tr>
                        <td>#<?php echo $order['id']; ?></td>
                        <td><?php echo htmlspecialchars($order['email']); ?></td>
                        <td class="currency">$<?php echo number_format($order['total_amount'], 2); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $order['status']; ?>">
                                <?php echo ucfirst($order['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Orders by Status Chart
const statusData = <?php echo json_encode($stats['orders_by_status']); ?>;
const statusLabels = statusData.map(s => s.status.charAt(0).toUpperCase() + s.status.slice(1));
const statusCounts = statusData.map(s => s.count);

const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: statusLabels,
        datasets: [{
            data: statusCounts,
            backgroundColor: [
                '#ffc107',
                '#28a745',
                '#17a2b8',
                '#dc3545'
            ],
            borderColor: '#fff',
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Revenue by Payment Method Chart
const paymentData = <?php echo json_encode($stats['payment_methods']); ?>;
const paymentLabels = paymentData.map(p => p.payment_method.replace('_', ' ').toUpperCase());
const paymentAmounts = paymentData.map(p => parseFloat(p.total));

const paymentCtx = document.getElementById('paymentChart').getContext('2d');
new Chart(paymentCtx, {
    type: 'bar',
    data: {
        labels: paymentLabels,
        datasets: [{
            label: 'Revenue ($)',
            data: paymentAmounts,
            backgroundColor: [
                '#667eea',
                '#764ba2',
                '#f093fb'
            ],
            borderRadius: 5,
            borderSkipped: false
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: true,
                position: 'top'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value.toFixed(2);
                    }
                }
            }
        }
    }
});
</script>

</body>
</html>