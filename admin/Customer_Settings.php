<?php
session_start();

// Include database & layout
require_once __DIR__ . '/../classes/db_connect.php';
require_once __DIR__ . '/../classes/Layout.php';

// Protect admin-only page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_customer'])) {
        // Update Customer Information
        $customer_id = intval($_POST['customer_id']);
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        
        if (empty($username) || empty($email)) {
            $error_message = "Username and email are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Invalid email format.";
        } else {
            // Check if username/email exists for other users
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
            $check_stmt->bind_param("ssi", $username, $email, $customer_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error_message = "Username or email already taken by another user.";
            } else {
                // Get old values for logging
                $old_stmt = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
                $old_stmt->bind_param("i", $customer_id);
                $old_stmt->execute();
                $old_data = $old_stmt->get_result()->fetch_assoc();
                
                // Update customer
                $update_stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, phone = ?, address = ? WHERE id = ? AND role = 'user'");
                $update_stmt->bind_param("ssssi", $username, $email, $phone, $address, $customer_id);
                
                if ($update_stmt->execute()) {
                    // Log activity
                    $log_stmt = $conn->prepare("INSERT INTO user_activity_log (user_id, admin_id, action_type, old_value, new_value, description) VALUES (?, ?, 'profile_update', ?, ?, 'Customer profile updated by admin')");
                    $old_value = json_encode(['username' => $old_data['username'], 'email' => $old_data['email']]);
                    $new_value = json_encode(['username' => $username, 'email' => $email]);
                    $log_stmt->bind_param("iiss", $customer_id, $admin_id, $old_value, $new_value);
                    $log_stmt->execute();
                    
                    // Send notification to customer
                    $notif_stmt = $conn->prepare("INSERT INTO user_notifications (user_id, title, message, type) VALUES (?, 'Profile Updated', 'Your profile information was updated by an administrator. Please review your account settings.', 'info')");
                    $notif_stmt->bind_param("i", $customer_id);
                    $notif_stmt->execute();
                    
                    $success_message = "Customer profile updated successfully!";
                } else {
                    $error_message = "Failed to update customer profile.";
                }
            }
        }
    } elseif (isset($_POST['reset_password'])) {
        // Reset Customer Password
        $customer_id = intval($_POST['customer_id']);
        $new_password = $_POST['new_password'];
        
        if (strlen($new_password) < 6) {
            $error_message = "Password must be at least 6 characters.";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'user'");
            $update_stmt->bind_param("si", $hashed_password, $customer_id);
            
            if ($update_stmt->execute()) {
                // Log activity
                $log_stmt = $conn->prepare("INSERT INTO user_activity_log (user_id, admin_id, action_type, description) VALUES (?, ?, 'password_reset', 'Password reset by admin')");
                $log_stmt->bind_param("ii", $customer_id, $admin_id);
                $log_stmt->execute();
                
                // Send notification
                $notif_stmt = $conn->prepare("INSERT INTO user_notifications (user_id, title, message, type) VALUES (?, '🔒 Password Reset', 'Your password has been reset by an administrator. Please login with your new password and change it immediately.', 'warning')");
                $notif_stmt->bind_param("i", $customer_id);
                $notif_stmt->execute();
                
                $success_message = "Customer password reset successfully!";
            } else {
                $error_message = "Failed to reset password.";
            }
        }
    } elseif (isset($_POST['delete_customer'])) {
        // Delete Customer Account
        $customer_id = intval($_POST['customer_id']);
        
        // Get customer info before deletion
        $info_stmt = $conn->prepare("SELECT username FROM users WHERE id = ? AND role = 'user'");
        $info_stmt->bind_param("i", $customer_id);
        $info_stmt->execute();
        $customer_info = $info_stmt->get_result()->fetch_assoc();
        
        if ($customer_info) {
            $delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'user'");
            $delete_stmt->bind_param("i", $customer_id);
            
            if ($delete_stmt->execute()) {
                $success_message = "Customer account deleted successfully!";
            } else {
                $error_message = "Failed to delete customer account.";
            }
        } else {
            $error_message = "Customer not found.";
        }
    } elseif (isset($_POST['send_notification'])) {
        // Send Custom Notification
        $customer_id = intval($_POST['customer_id']);
        $title = trim($_POST['notif_title']);
        $message = trim($_POST['notif_message']);
        $type = $_POST['notif_type'];
        
        if (empty($title) || empty($message)) {
            $error_message = "Title and message are required.";
        } else {
            $notif_stmt = $conn->prepare("INSERT INTO user_notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
            $notif_stmt->bind_param("isss", $customer_id, $title, $message, $type);
            
            if ($notif_stmt->execute()) {
                $success_message = "Notification sent successfully!";
            } else {
                $error_message = "Failed to send notification.";
            }
        }
    }
}

// Get customer statistics
$total_customers = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'")->fetch_assoc()['count'];
$active_orders = $conn->query("SELECT COUNT(DISTINCT o.user_id) as count FROM orders o WHERE o.status IN ('pending', 'processing')")->fetch_assoc()['count'];
$total_orders = $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'];
$total_revenue = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE status = 'completed'")->fetch_assoc()['total'] ?? 0;

// Get all customers with their stats
$customers_query = "
    SELECT 
        u.*,
        COUNT(DISTINCT o.id) as total_orders,
        COALESCE(SUM(o.total_amount), 0) as total_spent,
        COUNT(DISTINCT c.id) as cart_items,
        COUNT(DISTINCT w.id) as wishlist_items,
        MAX(o.created_at) as last_order
    FROM users u
    LEFT JOIN orders o ON u.id = o.user_id
    LEFT JOIN cart c ON u.id = c.user_id
    LEFT JOIN wishlist w ON u.id = w.user_id
    WHERE u.role = 'user'
    GROUP BY u.id
    ORDER BY u.created_at DESC
";
$customers_result = $conn->query($customers_query);

// Get selected customer details if editing
$selected_customer = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'user'");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $selected_customer = $stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Management</title>
    <link rel="stylesheet" href="../navbar_sidebar.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: white;
            color: #222;
            margin: 0;
            padding: 0;
        }

        .page-container {
            margin-left: 100px;
            padding: 30px;
            max-width: 1600px;
            width: 100%;
            box-sizing: border-box;
            margin-top: 35px;
        }
        
        .page-header {
            margin-top: 35px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
            text-align: center;
        }
        
        .page-header h2 {
            margin: 0 0 10px 0;
            font-size: 32px;
        }
        
        .page-header p {
            margin: 0;
            opacity: 0.95;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 4px solid;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.primary { border-color: #667eea; }
        .stat-card.success { border-color: #4caf50; }
        .stat-card.warning { border-color: #ff9800; }
        .stat-card.info { border-color: #2196f3; }
        
        .stat-card .icon {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .stat-card .number {
            font-size: 28px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-card .label {
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
        }
        
        /* Main Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .content-box {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .content-box h3 {
            margin: 0 0 20px 0;
            font-size: 22px;
            color: #333;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        /* Customer Table */
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        thead {
            background: #f8f9fa;
        }
        
        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #e0e0e0;
            font-size: 14px;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .customer-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .customer-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #667eea;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .badge {
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-primary { background: #e3f2fd; color: #2196f3; }
        .badge-success { background: #e8f5e9; color: #4caf50; }
        .badge-warning { background: #fff3e0; color: #ff9800; }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
        }
        
        .btn-success {
            background: #4caf50;
            color: white;
        }
        
        .btn-success:hover {
            background: #45a049;
        }
        
        .btn-warning {
            background: #ff9800;
            color: white;
        }
        
        .btn-warning:hover {
            background: #f57c00;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-sm {
            padding: 8px 15px;
            font-size: 12px;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .sidebar {
            position: fixed !important;
            z-index: 1000 !important;
        }

        .navbar {
            position: fixed !important;
            z-index: 1100 !important;
        }
        
        @media (max-width: 768px) {
            .page-container {
                margin-left: 0;
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

<?php Layout::navbar(); ?>
<?php Layout::sidebar(); ?>

<div class="page-container">
    
    <div class="page-header">
        <h2>👥 Customer Management</h2>
        <p>Manage customer accounts, orders, and notifications</p>
    </div>
    
    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <span>✓</span>
            <span><?= htmlspecialchars($success_message); ?></span>
        </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-error">
            <span>✗</span>
            <span><?= htmlspecialchars($error_message); ?></span>
        </div>
    <?php endif; ?>
    
    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card primary">
            <div class="icon">👥</div>
            <div class="number"><?= $total_customers; ?></div>
            <div class="label">Total Customers</div>
        </div>
        
        <div class="stat-card warning">
            <div class="icon">🛒</div>
            <div class="number"><?= $active_orders; ?></div>
            <div class="label">Active Customers</div>
        </div>
        
        <div class="stat-card info">
            <div class="icon">📦</div>
            <div class="number"><?= $total_orders; ?></div>
            <div class="label">Total Orders</div>
        </div>
        
        <div class="stat-card success">
            <div class="icon">💰</div>
            <div class="number">$<?= number_format($total_revenue, 2); ?></div>
            <div class="label">Total Revenue</div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="content-grid">
        
        <!-- Customer List -->
        <div class="content-box">
            <h3>Customer List</h3>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Email</th>
                            <th>Orders</th>
                            <th>Total Spent</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($customers_result->num_rows > 0): ?>
                            <?php while ($customer = $customers_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="customer-info">
                                            <div class="customer-avatar">
                                                <?= strtoupper(substr($customer['username'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <strong><?= htmlspecialchars($customer['username']); ?></strong>
                                                <?php if ($customer['total_orders'] > 5): ?>
                                                    <span class="badge badge-success">VIP</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($customer['email']); ?></td>
                                    <td>
                                        <span class="badge badge-primary"><?= $customer['total_orders']; ?> orders</span>
                                    </td>
                                    <td>
                                        <strong>$<?= number_format($customer['total_spent'], 2); ?></strong>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($customer['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?edit=<?= $customer['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 30px; color: #999;">
                                    No customers found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Management Panel -->
        <div class="content-box">
            <?php if ($selected_customer): ?>
                <h3>Edit Customer</h3>
                
                <!-- Update Profile -->
                <form method="POST" style="margin-bottom: 30px;">
                    <input type="hidden" name="customer_id" value="<?= $selected_customer['id']; ?>">
                    
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" value="<?= htmlspecialchars($selected_customer['username']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($selected_customer['email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($selected_customer['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address"><?= htmlspecialchars($selected_customer['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <button type="submit" name="update_customer" class="btn btn-primary">
                        💾 Update Profile
                    </button>
                </form>
                
                <!-- Reset Password -->
                <form method="POST" style="margin-bottom: 30px; padding-top: 20px; border-top: 2px solid #f0f0f0;">
                    <input type="hidden" name="customer_id" value="<?= $selected_customer['id']; ?>">
                    
                    <h4 style="margin-bottom: 15px;">🔒 Reset Password</h4>
                    
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" minlength="6" required>
                    </div>
                    
                    <button type="submit" name="reset_password" class="btn btn-warning">
                        Reset Password
                    </button>
                </form>
                
                <!-- Send Notification -->
                <form method="POST" style="margin-bottom: 30px; padding-top: 20px; border-top: 2px solid #f0f0f0;">
                    <input type="hidden" name="customer_id" value="<?= $selected_customer['id']; ?>">
                    
                    <h4 style="margin-bottom: 15px;">🔔 Send Notification</h4>
                    
                    <div class="form-group">
                        <label>Title</label>
                        <input type="text" name="notif_title" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Message</label>
                        <textarea name="notif_message" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Type</label>
                        <select name="notif_type">
                            <option value="info">Info</option>
                            <option value="success">Success</option>
                            <option value="warning">Warning</option>
                            <option value="danger">Danger</option>
                        </select>
                    </div>
                    
                    <button type="submit" name="send_notification" class="btn btn-success">
                        Send Notification
                    </button>
                </form>
                
                <!-- Delete Account -->
                <form method="POST" onsubmit="return confirm('Are you absolutely sure you want to delete this customer account? This action cannot be undone!');" style="padding-top: 20px; border-top: 2px solid #f0f0f0;">
                    <input type="hidden" name="customer_id" value="<?= $selected_customer['id']; ?>">
                    
                    <h4 style="margin-bottom: 15px; color: #dc3545;">⚠️ Danger Zone</h4>
                    <p style="color: #666; margin-bottom: 15px; font-size: 14px;">
                        This will permanently delete the customer account and all associated data.
                    </p>
                    
                    <button type="submit" name="delete_customer" class="btn btn-danger">
                        🗑️ Delete Customer
                    </button>
                </form>
                
                <a href="?" class="btn btn-primary" style="margin-top: 20px; display: block; text-align: center;">
                    ← Back to List
                </a>
                
            <?php else: ?>
                <h3>Quick Actions</h3>
                <p style="color: #666; margin-bottom: 20px;">
                    Select a customer from the list to edit their profile, reset password, send notifications, or delete their account.
                </p>
                
                <div style="padding: 30px; text-align: center; background: #f8f9fa; border-radius: 8px;">
                    <div style="font-size: 48px; margin-bottom: 10px;">👈</div>
                    <p style="color: #999;">Click "Edit" on any customer to get started</p>
                </div>
            <?php endif; ?>
        </div>
        
    </div>

</div>

</body>
</html>