<?php
// At the top of your sidebar file or main layout file, add this code to count unread messages
// This should be included where your sidebar is displayed

// Include database connection if not already included
if (!isset($conn)) {
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
}

// Check if we're on the Customer Support page
$is_support_page = (basename($_SERVER['PHP_SELF']) === 'Customer_Support.php');

// Get total unread messages from customers
// Show badge only if there are unread messages, regardless of page
$unread_query = "
    SELECT COUNT(*) as unread_count 
    FROM support_messages 
    WHERE is_admin = 0 AND is_read = 0
";
$result = $conn->query($unread_query);
$unread_data = $result->fetch_assoc();
$unread_count = $unread_data['unread_count'] ?? 0;


?>

<aside class="sidebar">
    <ul>
        <li><a href="Products.php" data-short="Products">Manage Products</a></li>
        <li><a href="Customer_Cart.php" data-short="Cart">Customer Cart</a></li>
        <li><a href="manage_Orders.php" data-short="Orders">Bank details</a></li>
        <li><a href="Customers_Account.php" data-short="Customers">Customer Accounts</a></li>
        <li><a href="verify-payment.php" data-short="Customers">Verify Payments</a></li>
        <li><a href="track-order.php">Track Orders</a></li>
        
        <!-- Customer Support with Badge - Shows only if there are unread messages -->
        <li>
            <a href="Customer_Support.php" class="support-link">
                Customer Support
                <?php if ($unread_count > 0): ?>
                    <span class="notification-badge" title="<?php echo $unread_count; ?> unread messages">
                        <?php echo $unread_count > 99 ? '99+' : $unread_count; ?>
                    </span>
                <?php endif; ?>
            </a>
        </li>

        <li><a href="Analytics.php" data-short="Analytics">Analytics</a></li>
        <li><a href="Customer_Settings.php" data-short="Settings">Customer Settings</a></li>
        <li><a href="profile.php" data-short="Profile">Profile</a></li>
        <li><a href="../logout.php" class="logout-btn">Logout</a></li>
    </ul>

    <style>
        /* Notification Badge Styles */
        .notification-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 24px;
            height: 24px;
            padding: 0 6px;
            margin-left: 8px;
            background-color: #e74c3c;
            color: white;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            animation: pulse 2s infinite;
        }



        .support-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
        }

        @keyframes pulse {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.7);
            }
            50% {
                box-shadow: 0 0 0 10px rgba(231, 76, 60, 0);
            }
        }

        /* Responsive design for sidebar */
        @media (max-width: 768px) {
            .notification-badge,
            .ticket-badge {
                min-width: 20px;
                height: 20px;
                font-size: 11px;
                padding: 0 4px;
                margin-left: 4px;
            }
        }

        /* When sidebar is collapsed, show badges only */
        .sidebar.collapsed .support-link {
            justify-content: center;
        }

        .sidebar.collapsed .support-link > span:not(.notification-badge):not(.ticket-badge) {
            display: none;
        }
    </style>
</aside>