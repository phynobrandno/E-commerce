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

$success_message = '';
$error_message = '';

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    try {
        $order_id = $_POST['order_id'];
        $new_status = $_POST['status'];
        
        $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_status, $order_id]);
        
        $success_message = "Order status updated successfully!";
    } catch (PDOException $e) {
        $error_message = "Error updating order: " . $e->getMessage();
    }
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Filter by status
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$where_conditions = [];
$params = [];

if ($status_filter !== 'all') {
    $where_conditions[] = "o.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(u.username LIKE ? OR u.email LIKE ? OR o.id LIKE ? OR o.phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total records for pagination
try {
    $count_sql = "SELECT COUNT(*) as total 
                  FROM orders o
                  JOIN users u ON o.user_id = u.id
                  $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    $error_message = "Error counting records: " . $e->getMessage();
}

// Fetch orders with user information
try {
    $sql = "SELECT 
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
                u.email,
                COUNT(oi.id) as item_count
            FROM orders o
            JOIN users u ON o.user_id = u.id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            $where_clause
            GROUP BY o.id
            ORDER BY o.created_at DESC
            LIMIT $records_per_page OFFSET $offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error fetching orders: " . $e->getMessage();
    $orders = [];
}

// Get order statistics
try {
    $stats_sql = "SELECT 
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                    SUM(total_amount) as total_revenue
                  FROM orders";
    $stats = $pdo->query($stats_sql)->fetch();
} catch (PDOException $e) {
    $error_message = "Error fetching statistics: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Orders - Admin</title>
    <link rel="stylesheet" href="../navbar_sidebar.css">
    <style>
        .content {
            margin-left: 100px;
            width: 90%;            
            padding: 20px;
            margin-top: 60px;
        }
        
        .orders-container {
            max-width: 1400px;
            margin: 0 auto;
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 13px;
            text-transform: uppercase;
        }
        
        .stat-card .stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #333;
        }
        
        .filters-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .filters-form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: end;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
            font-size: 14px;
        }
        
        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        
        .orders-table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .orders-table thead {
            background-color: #f8f9fa;
        }
        
        .orders-table th {
            padding: 15px;
            text-align: left;
            font-weight: bold;
            color: #333;
            border-bottom: 2px solid #dee2e6;
            font-size: 14px;
        }
        
        .orders-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #dee2e6;
            font-size: 14px;
        }
        
        .orders-table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
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
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
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
        
        .btn-view {
            background-color: #17a2b8;
            color: white;
        }
        
        .btn-view:hover {
            background-color: #138496;
        }
        
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background-color: #218838;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            padding: 20px;
        }
        
        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }
        
        .pagination a:hover {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .pagination .active {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .no-orders {
            text-align: center;
            padding: 40px;
            color: #666;
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
        
        .modal-header {
            margin-bottom: 20px;
        }
        
        .modal-header h2 {
            margin: 0;
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
        
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 10px;
            }
            
            .orders-table-container {
                overflow-x: auto;
            }
            
            .filters-form {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<?php Layout::navbar(); ?>
<?php Layout::sidebar(); ?>

<div class="content">
    <div class="orders-container">
        <h1>Customer Orders</h1>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Orders</h3>
                <div class="stat-number"><?php echo $stats['total_orders'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h3>Pending</h3>
                <div class="stat-number" style="color: #856404;"><?php echo $stats['pending'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h3>Processing</h3>
                <div class="stat-number" style="color: #084298;"><?php echo $stats['processing'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h3>Completed</h3>
                <div class="stat-number" style="color: #0f5132;"><?php echo $stats['completed'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h3>Cancelled</h3>
                <div class="stat-number" style="color: #842029;"><?php echo $stats['cancelled'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Revenue</h3>
                <div class="stat-number" style="color: #28a745;">$<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters-section">
            <form method="GET" action="" class="filters-form">
                <div class="filter-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" 
                           placeholder="Order ID, customer name, email..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="filter-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="view_orders.php" class="btn btn-success">Reset</a>
                </div>
            </form>
        </div>
        
        <!-- Orders Table -->
        <div class="orders-table-container">
            <?php if (empty($orders)): ?>
                <div class="no-orders">
                    <h3>No orders found</h3>
                    <p>There are no orders matching your criteria.</p>
                </div>
            <?php else: ?>
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Total Amount</th>
                            <th>Payment Method</th>
                            <th>Status</th>
                            <th>Order Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($order['id']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($order['username']); ?></strong><br>
                                    <small style="color: #666;"><?php echo htmlspecialchars($order['email']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($order['item_count']); ?> item(s)</td>
                                <td><strong>$<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                <td><?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($order['payment_method']))); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo htmlspecialchars($order['status']); ?>">
                                        <?php echo htmlspecialchars($order['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-view">View</a>
                                        <button onclick="openStatusModal(<?php echo $order['id']; ?>, '<?php echo $order['status']; ?>')" 
                                                class="btn btn-primary">Update</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Status Update Modal -->
<div id="statusModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span class="close" onclick="closeStatusModal()">&times;</span>
            <h2>Update Order Status</h2>
        </div>
        <form method="POST" action="">
            <input type="hidden" id="modal_order_id" name="order_id">
            <div class="filter-group">
                <label for="modal_status">New Status</label>
                <select id="modal_status" name="status" required>
                    <option value="pending">Pending</option>
                    <option value="processing">Processing</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div style="margin-top: 20px;">
                <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                <button type="button" onclick="closeStatusModal()" class="btn btn-success">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openStatusModal(orderId, currentStatus) {
    document.getElementById('modal_order_id').value = orderId;
    document.getElementById('modal_status').value = currentStatus;
    document.getElementById('statusModal').style.display = 'block';
}

function closeStatusModal() {
    document.getElementById('statusModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    var modal = document.getElementById('statusModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}
</script>

</body>
</html>