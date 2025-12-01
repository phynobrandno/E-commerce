<?php
// admin/track_orders.php
session_start();

include "../classes/Layout.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
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

// Handle AJAX tracking update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tracking'])) {
    header('Content-Type: application/json');
    
    $order_id = (int)$_POST['order_id'];
    $shipment_status = $_POST['shipment_status'];
    $tracking_number = trim($_POST['tracking_number']);
    $carrier = $_POST['carrier'];
    $estimated_delivery = $_POST['estimated_delivery'];
    $location = trim($_POST['location']);
    $description = trim($_POST['description']);

    try {
        $stmt = $conn->prepare("
            UPDATE orders 
            SET shipment_status = ?, tracking_number = ?, carrier = ?, 
                estimated_delivery = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->bind_param("ssssi", $shipment_status, $tracking_number, $carrier, $estimated_delivery, $order_id);
        
        if ($stmt->execute()) {
            // Add to tracking history
            $history_stmt = $conn->prepare("
                INSERT INTO order_tracking_history (order_id, status, location, description) 
                VALUES (?, ?, ?, ?)
            ");
            $history_stmt->bind_param("isss", $order_id, $shipment_status, $location, $description);
            $history_stmt->execute();
            $history_stmt->close();
            
            echo json_encode([
                'success' => true,
                'message' => 'Tracking updated successfully!',
                'order_id' => $order_id,
                'status' => $shipment_status
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update tracking'
            ]);
        }
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
    exit();
}

// Get filters
$status_filter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

// Build query - Only show orders where payment is confirmed (completed)
$query = "
    SELECT o.id, o.user_id, o.total_amount, o.status, o.shipment_status,
           o.tracking_number, o.carrier, o.estimated_delivery, o.created_at,
           u.username, u.email, COUNT(oi.id) as item_count,
           p.status as payment_status, p.transaction_id
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN payments p ON o.id = p.order_id
    WHERE o.status = 'completed' AND p.status = 'completed'
";

$params = [];
$types = '';

if ($status_filter !== 'all') {
    $query .= " AND o.shipment_status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($search)) {
    $search_term = '%' . $search . '%';
    $query .= " AND (o.tracking_number LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'sss';
}

$query .= " GROUP BY o.id ORDER BY o.created_at DESC";

$orders = [];
if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $result = $conn->query($query);
    $orders = $result->fetch_all(MYSQLI_ASSOC);
}

// Get stats - Only for orders with confirmed payments
$stats_query = "
    SELECT 
        COUNT(DISTINCT o.id) as total,
        SUM(CASE WHEN o.shipment_status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN o.shipment_status = 'shipped' THEN 1 ELSE 0 END) as shipped,
        SUM(CASE WHEN o.shipment_status = 'in_transit' THEN 1 ELSE 0 END) as in_transit,
        SUM(CASE WHEN o.shipment_status = 'delivered' THEN 1 ELSE 0 END) as delivered
    FROM orders o
    LEFT JOIN payments p ON o.id = p.order_id
    WHERE o.status = 'completed' AND p.status = 'completed'
";
$result = $conn->query($stats_query);
$stats = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Orders - Admin</title>
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

        .container {
            max-width: 1600px;
            margin: 0 auto;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 32px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            transition: all 0.35s ease-in-out;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #667eea;
        }

        .stat-label {
            font-size: 12px;
            font-weight: 600;
            color: #95a5a6;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #2c3e50;
        }

        .stat-card.pending { border-left-color: #f39c12; }
        .stat-card.shipped { border-left-color: #3498db; }
        .stat-card.in-transit { border-left-color: #9b59b6; }
        .stat-card.delivered { border-left-color: #2ecc71; }

        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            transition: all 0.35s ease-in-out;
        }

        .filter-content {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filter-group label {
            font-size: 12px;
            font-weight: 600;
            color: #2c3e50;
            text-transform: uppercase;
        }

        .filter-group select,
        .filter-group input {
            padding: 10px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            min-width: 150px;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .orders-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: all 0.35s ease-in-out;
        }

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
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #e0e0e0;
            font-size: 12px;
            text-transform: uppercase;
        }

        table td {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            color: #555;
        }

        table tbody tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-processed { background: #d1ecf1; color: #0c5460; }
        .status-shipped { background: #cce5ff; color: #004085; }
        .status-in_transit { background: #e2e3e5; color: #383d41; }
        .status-delivered { background: #d4edda; color: #155724; }

        .btn-update {
            background: #667eea;
            color: white;
            padding: 6px 12px;
            font-size: 11px;
        }

        .btn-update:hover {
            background: #5568d3;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.15);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            font-size: 20px;
            color: #2c3e50;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 14px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }

        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                width: 100%;
            }

            .filter-content {
                flex-direction: column;
            }

            .filter-group {
                width: 100%;
            }

            .filter-group select,
            .filter-group input {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>

<?php Layout::navbar(); ?>
<?php Layout::sidebar(); ?>

<div class="content">
    <div class="container">
        <div class="page-header">
            <h1>📦 Track Orders</h1>
            <p>Manage shipment tracking for customer orders</p>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="success-message">✓ <?= $_SESSION['success']; ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card pending">
                <div class="stat-label">Pending</div>
                <div class="stat-number"><?= $stats['pending'] ?? 0; ?></div>
            </div>
            <div class="stat-card shipped">
                <div class="stat-label">Shipped</div>
                <div class="stat-number"><?= $stats['shipped'] ?? 0; ?></div>
            </div>
            <div class="stat-card in-transit">
                <div class="stat-label">In Transit</div>
                <div class="stat-number"><?= $stats['in_transit'] ?? 0; ?></div>
            </div>
            <div class="stat-card delivered">
                <div class="stat-label">Delivered</div>
                <div class="stat-number"><?= $stats['delivered'] ?? 0; ?></div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <div class="filter-content">
                <div class="filter-group">
                    <label>Status</label>
                    <select onchange="document.location='?status=' + this.value">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="processed" <?= $status_filter === 'processed' ? 'selected' : ''; ?>>Processed</option>
                        <option value="shipped" <?= $status_filter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                        <option value="in_transit" <?= $status_filter === 'in_transit' ? 'selected' : ''; ?>>In Transit</option>
                        <option value="delivered" <?= $status_filter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" placeholder="Tracking # or Username..." value="<?= htmlspecialchars($search); ?>" 
                           onchange="document.location='?search=' + encodeURIComponent(this.value)">
                </div>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="orders-table">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Tracking #</th>
                            <th>Status</th>
                            <th>Carrier</th>
                            <th>Est. Delivery</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><strong>#<?= $order['id']; ?></strong></td>
                            <td>
                                <strong><?= htmlspecialchars(substr($order['username'], 0, 20)); ?></strong><br>
                                <small style="color: #999;"><?= htmlspecialchars($order['email']); ?></small>
                            </td>
                            <td><?= $order['tracking_number'] ? htmlspecialchars($order['tracking_number']) : 'N/A'; ?></td>
                            <td>
                                <span class="status-badge status-<?= $order['shipment_status']; ?>">
                                    <?= ucfirst(str_replace('_', ' ', $order['shipment_status'])); ?>
                                </span>
                            </td>
                            <td><?= $order['carrier'] ? htmlspecialchars($order['carrier']) : 'N/A'; ?></td>
                            <td><?= $order['estimated_delivery'] ? date('M d, Y', strtotime($order['estimated_delivery'])) : 'N/A'; ?></td>
                            <td>
                                <button class="btn btn-update" onclick="openTrackingModal(<?= $order['id']; ?>, '<?= $order['shipment_status']; ?>', '<?= htmlspecialchars($order['tracking_number']); ?>', '<?= htmlspecialchars($order['carrier']); ?>', '<?= $order['estimated_delivery']; ?>')">
                                    Update
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Tracking Modal -->
<div class="modal" id="trackingModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Update Tracking Information</h2>
            <button type="button" class="close-modal" onclick="closeTrackingModal()">&times;</button>
        </div>
        <form method="POST" id="trackingForm" onsubmit="submitTrackingForm(event)">
            <input type="hidden" name="update_tracking" value="1">
            <input type="hidden" name="order_id" id="order_id" value="">
            
            <div class="form-group">
                <label>Shipment Status *</label>
                <select name="shipment_status" id="shipment_status" required>
                    <option value="">-- Select Status --</option>
                    <option value="pending">Pending</option>
                    <option value="processed">Processed</option>
                    <option value="shipped">Shipped</option>
                    <option value="in_transit">In Transit</option>
                    <option value="out_for_delivery">Out for Delivery</option>
                    <option value="delivered">Delivered</option>
                </select>
            </div>

            <div class="form-group">
                <label>Tracking Number *</label>
                <input type="text" name="tracking_number" id="tracking_number" value="" required placeholder="Enter tracking number">
            </div>

            <div class="form-group">
                <label>Carrier *</label>
                <select name="carrier" id="carrier" required>
                    <option value="">-- Select Carrier --</option>
                    <option value="FedEx">FedEx</option>
                    <option value="UPS">UPS</option>
                    <option value="DHL">DHL</option>
                    <option value="Local Courier">Local Courier</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div class="form-group">
                <label>Estimated Delivery Date</label>
                <input type="date" name="estimated_delivery" id="estimated_delivery" value="">
            </div>

            <div class="form-group">
                <label>Current Location</label>
                <input type="text" name="location" id="location" value="" placeholder="e.g., Distribution Center, New York">
            </div>

            <div class="form-group">
                <label>Update Description</label>
                <textarea name="description" id="description" rows="3" placeholder="What's the latest update on this shipment?"></textarea>
            </div>

            <div class="form-actions">
                <button type="button" class="btn" onclick="closeTrackingModal()" style="background: #ccc; color: #333;">Cancel</button>
                <button type="submit" class="btn btn-primary" id="submitBtn">Update Tracking</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openTrackingModal(orderId, status, tracking, carrier, estimatedDelivery) {
        document.getElementById('order_id').value = orderId;
        document.getElementById('shipment_status').value = status || '';
        document.getElementById('tracking_number').value = tracking || '';
        document.getElementById('carrier').value = carrier || '';
        document.getElementById('estimated_delivery').value = estimatedDelivery || '';
        document.getElementById('location').value = '';
        document.getElementById('description').value = '';
        document.getElementById('trackingModal').classList.add('active');
    }

    function closeTrackingModal() {
        document.getElementById('trackingModal').classList.remove('active');
    }

    function submitTrackingForm(e) {
        e.preventDefault();
        
        const form = document.getElementById('trackingForm');
        const submitBtn = document.getElementById('submitBtn');
        const formData = new FormData(form);
        
        // Disable button during submission
        submitBtn.disabled = true;
        submitBtn.textContent = 'Updating...';
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                alert('✓ ' + data.message);
                
                // Close modal
                closeTrackingModal();
                
                // Reload the page to show updated data
                location.reload();
            } else {
                alert('❌ ' + data.message);
                submitBtn.disabled = false;
                submitBtn.textContent = 'Update Tracking';
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
            submitBtn.disabled = false;
            submitBtn.textContent = 'Update Tracking';
        });
    }

    // Close modal when clicking outside
    document.getElementById('trackingModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeTrackingModal();
        }
    });

    // Detect sidebar hover
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