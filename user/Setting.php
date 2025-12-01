<?php
session_start();

// Include database & layout
require_once __DIR__ . '/../classes/db_connect.php';
require_once __DIR__ . '/../classes/UserLayout.php';

// Protect user-only page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Mark notification as read
if (isset($_GET['mark_read'])) {
    $notif_id = intval($_GET['mark_read']);
    $stmt = $conn->prepare("UPDATE user_notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notif_id, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: settings.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Update Profile Information
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        
        // Validate inputs
        if (empty($username) || empty($email)) {
            $error_message = "Username and email are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Invalid email format.";
        } else {
            // Check if username/email already exists for other users
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
            $check_stmt->bind_param("ssi", $username, $email, $user_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error_message = "Username or email already taken.";
            } else {
                // Update user information
                $update_stmt = $conn->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
                $update_stmt->bind_param("ssi", $username, $email, $user_id);
                
                if ($update_stmt->execute()) {
                    $_SESSION['username'] = $username;
                    $success_message = "Profile updated successfully!";
                } else {
                    $error_message = "Failed to update profile.";
                }
                $update_stmt->close();
            }
            $check_stmt->close();
        }
    } elseif (isset($_POST['change_password'])) {
        // Change Password
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = "All password fields are required.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match.";
        } elseif (strlen($new_password) < 6) {
            $error_message = "Password must be at least 6 characters.";
        } else {
            // Verify current password
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            if (password_verify($current_password, $user['password'])) {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update_stmt->bind_param("si", $hashed_password, $user_id);
                
                if ($update_stmt->execute()) {
                    $success_message = "Password changed successfully!";
                } else {
                    $error_message = "Failed to change password.";
                }
                $update_stmt->close();
            } else {
                $error_message = "Current password is incorrect.";
            }
            $stmt->close();
        }
    } elseif (isset($_POST['delete_account'])) {
        // Delete Account
        $password = $_POST['delete_password'];
        
        if (empty($password)) {
            $error_message = "Password is required to delete account.";
        } else {
            // Verify password
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                // Delete user (cascade will delete cart and wishlist items)
                $delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $delete_stmt->bind_param("i", $user_id);
                
                if ($delete_stmt->execute()) {
                    session_destroy();
                    header("Location: ../index.php?account_deleted=1");
                    exit();
                } else {
                    $error_message = "Failed to delete account.";
                }
                $delete_stmt->close();
            } else {
                $error_message = "Incorrect password.";
            }
            $stmt->close();
        }
    }
}

// Fetch current user data
$stmt = $conn->prepare("SELECT username, email, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Get user statistics
$cart_count = $conn->query("SELECT COUNT(*) as count FROM cart WHERE user_id = $user_id")->fetch_assoc()['count'];
$wishlist_count = $conn->query("SELECT COUNT(*) as count FROM wishlist WHERE user_id = $user_id")->fetch_assoc()['count'];

// Get unread notifications
$notif_stmt = $conn->prepare("SELECT * FROM user_notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notifications = $notif_stmt->get_result();
$notif_stmt->close();

// Get unread count
$unread_count = $conn->query("SELECT COUNT(*) as count FROM user_notifications WHERE user_id = $user_id AND is_read = 0")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account Settings</title>
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
        
        /* Notifications Section */
        .notifications-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .notifications-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .notifications-header h3 {
            margin: 0;
            font-size: 18px;
            color: #333;
        }
        
        .badge-count {
            background: #dc3545;
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .notification-item {
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            border-left: 4px solid;
            display: flex;
            justify-content: space-between;
            align-items: start;
            gap: 15px;
        }
        
        .notification-item.info {
            background: #e3f2fd;
            border-color: #2196f3;
        }
        
        .notification-item.success {
            background: #e8f5e9;
            border-color: #4caf50;
        }
        
        .notification-item.warning {
            background: #fff3e0;
            border-color: #ff9800;
        }
        
        .notification-item.danger {
            background: #ffebee;
            border-color: #f44336;
        }
        
        .notification-item.read {
            opacity: 0.6;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-title {
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }
        
        .notification-message {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .notification-time {
            font-size: 12px;
            color: #999;
        }
        
        .notification-actions {
            display: flex;
            gap: 5px;
        }
        
        .btn-mark-read {
            padding: 5px 10px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
            text-decoration: none;
            white-space: nowrap;
        }
        
        .btn-mark-read:hover {
            background: #5568d3;
        }
        
        .no-notifications {
            text-align: center;
            padding: 30px;
            color: #999;
        }
        
        .settings-grid {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 968px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .sidebar-menu {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            height: fit-content;
        }
        
        .sidebar-menu h3 {
            margin: 0 0 20px 0;
            font-size: 18px;
            color: #333;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .menu-item {
            padding: 12px 15px;
            margin-bottom: 8px;
            border-radius: 8px;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #666;
        }
        
        .menu-item:hover {
            background: #f8f9fa;
            color: #667eea;
        }
        
        .menu-item.active {
            background: #667eea;
            color: white;
        }
        
        .user-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        
        .stat-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .stat-item .number {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .stat-item .label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
        
        .settings-content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .settings-section {
            display: none;
        }
        
        .settings-section.active {
            display: block;
        }
        
        .settings-section h3 {
            margin: 0 0 20px 0;
            font-size: 22px;
            color: #333;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group input[readonly] {
            background: #f8f9fa;
            color: #666;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: 0.3s;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .danger-zone {
            margin-top: 40px;
            padding: 20px;
            background: #fff5f5;
            border: 2px solid #ff4757;
            border-radius: 12px;
        }
        
        .danger-zone h4 {
            color: #dc3545;
            margin: 0 0 15px 0;
        }
        
        .danger-zone p {
            color: #666;
            margin-bottom: 15px;
        }
        
        @media (max-width: 768px) {
            .page-container {
                margin-left: 0;
                padding: 20px;
            }
            
            .user-stats {
                grid-template-columns: 1fr;
            }
        }
        
        .sidebar {
            position: fixed !important;
            z-index: 1000 !important;
        }

        .navbar {
            position: fixed !important;
            z-index: 1100 !important;
        }
    </style>
</head>
<body>

<?php UserLayout::navbar(); ?>
<?php UserLayout::sidebar(); ?>

<div class="page-container">
    
    <div class="page-header">
        <h2>⚙️ Account Settings</h2>
        <p>Manage your account information and preferences</p>
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
    
    <!-- Notifications Section -->
    <?php if ($notifications->num_rows > 0): ?>
    <div class="notifications-section">
        <div class="notifications-header">
            <h3>🔔 Recent Notifications</h3>
            <?php if ($unread_count > 0): ?>
                <span class="badge-count"><?= $unread_count; ?> New</span>
            <?php endif; ?>
        </div>
        
        <?php while($notif = $notifications->fetch_assoc()): ?>
            <div class="notification-item <?= $notif['type']; ?> <?= $notif['is_read'] ? 'read' : ''; ?>">
                <div class="notification-content">
                    <div class="notification-title"><?= htmlspecialchars($notif['title']); ?></div>
                    <div class="notification-message"><?= htmlspecialchars($notif['message']); ?></div>
                    <div class="notification-time"><?= date('M d, Y g:i A', strtotime($notif['created_at'])); ?></div>
                </div>
                <?php if (!$notif['is_read']): ?>
                    <div class="notification-actions">
                        <a href="?mark_read=<?= $notif['id']; ?>" class="btn-mark-read">Mark Read</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>
    
    <div class="settings-grid">
        
        <!-- Sidebar Menu -->
        <div class="sidebar-menu">
            <h3>Settings Menu</h3>
            
            <div class="menu-item active" onclick="showSection('profile')">
                👤 Profile Information
            </div>
            <div class="menu-item" onclick="showSection('security')">
                🔒 Security & Password
            </div>
            <div class="menu-item" onclick="showSection('account')">
                ⚠️ Account Management
            </div>
            
            <div class="user-stats">
                <div class="stat-item">
                    <div class="number"><?= $cart_count; ?></div>
                    <div class="label">Cart Items</div>
                </div>
                <div class="stat-item">
                    <div class="number"><?= $wishlist_count; ?></div>
                    <div class="label">Wishlist</div>
                </div>
            </div>
        </div>
        
        <!-- Settings Content -->
        <div class="settings-content">
            
            <!-- Profile Section -->
            <div id="profile" class="settings-section active">
                <h3>Profile Information</h3>
                
                <form method="POST">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" value="<?= htmlspecialchars($user['username']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($user['email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Member Since</label>
                        <input type="text" value="<?= date('F d, Y', strtotime($user['created_at'])); ?>" readonly>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        💾 Save Changes
                    </button>
                </form>
            </div>
            
            <!-- Security Section -->
            <div id="security" class="settings-section">
                <h3>Change Password</h3>
                
                <form method="POST">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" minlength="6" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" minlength="6" required>
                    </div>
                    
                    <button type="submit" name="change_password" class="btn btn-primary">
                        🔒 Change Password
                    </button>
                </form>
            </div>
            
            <!-- Account Management Section -->
            <div id="account" class="settings-section">
                <h3>Account Management</h3>
                
                <p style="color: #666; margin-bottom: 30px;">
                    Manage your account settings and data. You can export your data or permanently delete your account.
                </p>
                
                <div class="danger-zone">
                    <h4>⚠️ Danger Zone</h4>
                    <p>Once you delete your account, there is no going back. This will permanently delete your account, cart items, and wishlist.</p>
                    
                    <form method="POST" onsubmit="return confirm('Are you absolutely sure? This action cannot be undone!');">
                        <div class="form-group">
                            <label>Enter your password to confirm</label>
                            <input type="password" name="delete_password" required>
                        </div>
                        
                        <button type="submit" name="delete_account" class="btn btn-danger">
                            🗑️ Delete My Account
                        </button>
                    </form>
                </div>
            </div>
            
        </div>
    </div>

</div>

<script>
function showSection(sectionId) {
    // Hide all sections
    document.querySelectorAll('.settings-section').forEach(section => {
        section.classList.remove('active');
    });
    
    // Remove active from all menu items
    document.querySelectorAll('.menu-item').forEach(item => {
        item.classList.remove('active');
    });
    
    // Show selected section
    document.getElementById(sectionId).classList.add('active');
    
    // Add active to clicked menu item
    event.target.classList.add('active');
}
</script>

</body>
</html>