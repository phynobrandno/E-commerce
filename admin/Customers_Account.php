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

$success_message = '';
$error_message = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_user'])) {
        $user_id = intval($_POST['user_id']);
        
        // Prevent admin from deleting themselves
        if ($user_id == $_SESSION['user_id']) {
            $error_message = "You cannot delete your own account!";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'user'");
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                $success_message = "Customer deleted successfully!";
            } else {
                $error_message = "Failed to delete customer.";
            }
            $stmt->close();
        }
    } elseif (isset($_POST['update_status'])) {
        $user_id = intval($_POST['user_id']);
        $new_role = $_POST['new_role'] === 'admin' ? 'admin' : 'user';
        
        // Prevent changing own role
        if ($user_id == $_SESSION['user_id']) {
            $error_message = "You cannot change your own role!";
        } else {
            $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->bind_param("si", $new_role, $user_id);
            
            if ($stmt->execute()) {
                $success_message = "User role updated successfully!";
            } else {
                $error_message = "Failed to update user role.";
            }
            $stmt->close();
        }
    } elseif (isset($_POST['reset_password'])) {
        $user_id = intval($_POST['user_id']);
        $new_password = password_hash('password123', PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $new_password, $user_id);
        
        if ($stmt->execute()) {
            $success_message = "Password reset to 'password123' successfully!";
        } else {
            $error_message = "Failed to reset password.";
        }
        $stmt->close();
    }
}

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'all';

// Build query
$query = "SELECT u.*, 
          (SELECT COUNT(*) FROM cart WHERE user_id = u.id) as cart_count,
          (SELECT COUNT(*) FROM wishlist WHERE user_id = u.id) as wishlist_count
          FROM users u WHERE 1=1";

if ($search !== '') {
    $search_term = $conn->real_escape_string($search);
    $query .= " AND (u.username LIKE '%$search_term%' OR u.email LIKE '%$search_term%')";
}

if ($role_filter !== 'all') {
    $role_filter_safe = $conn->real_escape_string($role_filter);
    $query .= " AND u.role = '$role_filter_safe'";
}

$query .= " ORDER BY u.created_at DESC";

$result = $conn->query($query);

// Get statistics
$total_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'")->fetch_assoc()['count'];
$total_admins = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")->fetch_assoc()['count'];
$new_users_month = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'user' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Accounts Management</title>
    <link rel="stylesheet" href="../navbar_sidebar.css">
    <style>
        .content {
            margin-left: 100px;
            width: 90%;     
            padding: 30px;
            margin-top: 70px;
        }
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .stat-card .icon {
            font-size: 40px;
            width: 70px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
        }
        
        .stat-card.blue .icon {
            background: #e3f2fd;
        }
        
        .stat-card.purple .icon {
            background: #f3e5f5;
        }
        
        .stat-card.green .icon {
            background: #e8f5e9;
        }
        
        .stat-card .info h3 {
            margin: 0 0 5px 0;
            font-size: 28px;
            color: #333;
        }
        
        .stat-card .info p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }
        
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-section input[type="text"],
        .filter-section select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            flex: 1;
            min-width: 200px;
        }
        
        .filter-section button {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: 0.3s;
        }
        
        .filter-section button:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: #f8f9fa;
        }
        
        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-user {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-admin {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        
        .btn-info:hover {
            background: #138496;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #000;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
        }
        
        .modal-content h3 {
            margin: 0 0 20px 0;
            color: #333;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-section {
                flex-direction: column;
            }
            
            .filter-section input,
            .filter-section select,
            .filter-section button {
                width: 100%;
            }
            
            .table-container {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>

<?php Layout::navbar(); ?>
<?php Layout::sidebar(); ?>

<div class="content">
    
    <div class="page-header">
        <h2>👥 Customer Accounts Management</h2>
        <p>Manage all customer accounts and permissions</p>
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
        <div class="stat-card blue">
            <div class="icon">👥</div>
            <div class="info">
                <h3><?= $total_users; ?></h3>
                <p>Total Customers</p>
            </div>
        </div>
        
        <div class="stat-card purple">
            <div class="icon">👑</div>
            <div class="info">
                <h3><?= $total_admins; ?></h3>
                <p>Total Admins</p>
            </div>
        </div>
        
        <div class="stat-card green">
            <div class="icon">📈</div>
            <div class="info">
                <h3><?= $new_users_month; ?></h3>
                <p>New This Month</p>
            </div>
        </div>
    </div>
    
    <!-- Filter Section -->
    <div class="filter-section">
        <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; width: 100%;">
            <input type="text" name="search" placeholder="🔍 Search by username or email..." value="<?= htmlspecialchars($search); ?>">
            
            <select name="role">
                <option value="all" <?= $role_filter === 'all' ? 'selected' : ''; ?>>All Roles</option>
                <option value="user" <?= $role_filter === 'user' ? 'selected' : ''; ?>>Customers Only</option>
                <option value="admin" <?= $role_filter === 'admin' ? 'selected' : ''; ?>>Admins Only</option>
            </select>
            
            <button type="submit">Filter</button>
            <a href="customers_accounts.php" style="text-decoration: none; display: inline-block; background: #6c757d; color: white; padding: 10px 25px; border-radius: 8px; font-weight: 600;">Clear</a>
        </form>
    </div>
    
    <!-- Customers Table -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Cart</th>
                    <th>Wishlist</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($user = $result->fetch_assoc()): ?>
                        <tr>
                            <td>#<?= $user['id']; ?></td>
                            <td><strong><?= htmlspecialchars($user['username']); ?></strong></td>
                            <td><?= htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="badge badge-<?= $user['role']; ?>">
                                    <?= $user['role'] === 'admin' ? '👑 Admin' : '👤 User'; ?>
                                </span>
                            </td>
                            <td><?= $user['cart_count']; ?> items</td>
                            <td><?= $user['wishlist_count']; ?> items</td>
                            <td><?= date('M d, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-sm btn-info" onclick="openRoleModal(<?= $user['id']; ?>, '<?= htmlspecialchars($user['username']); ?>', '<?= $user['role']; ?>')">
                                        Change Role
                                    </button>
                                    
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Reset password to default (password123)?');">
                                        <input type="hidden" name="user_id" value="<?= $user['id']; ?>">
                                        <button type="submit" name="reset_password" class="btn btn-sm btn-warning">
                                            Reset Password
                                        </button>
                                    </form>
                                    
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                            <input type="hidden" name="user_id" value="<?= $user['id']; ?>">
                                            <button type="submit" name="delete_user" class="btn btn-sm btn-danger">
                                                Delete
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="no-data">
                            No customers found. Try adjusting your filters.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<!-- Role Change Modal -->
<div id="roleModal" class="modal">
    <div class="modal-content">
        <h3>Change User Role</h3>
        <form method="POST">
            <input type="hidden" name="user_id" id="modal_user_id">
            
            <div class="form-group">
                <label>Username</label>
                <input type="text" id="modal_username" readonly style="background: #f8f9fa; padding: 10px; border: 1px solid #ddd; border-radius: 8px; width: 100%; box-sizing: border-box;">
            </div>
            
            <div class="form-group">
                <label>New Role</label>
                <select name="new_role" id="modal_role">
                    <option value="user">User (Customer)</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" name="update_status" class="btn btn-info" style="flex: 1;">
                    Update Role
                </button>
                <button type="button" class="btn btn-danger" onclick="closeRoleModal()" style="flex: 1;">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openRoleModal(userId, username, currentRole) {
    document.getElementById('modal_user_id').value = userId;
    document.getElementById('modal_username').value = username;
    document.getElementById('modal_role').value = currentRole;
    document.getElementById('roleModal').classList.add('active');
}

function closeRoleModal() {
    document.getElementById('roleModal').classList.remove('active');
}

// Close modal when clicking outside
document.getElementById('roleModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeRoleModal();
    }
});
</script>

</body>
</html>