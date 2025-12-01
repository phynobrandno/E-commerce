<?php
// Add this at the top of your sidebar file or main layout file

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

// Get current user ID from session
$current_user_id = $_SESSION['id'] ?? $_SESSION['user_id'] ?? 0;

// Get unread support messages for current user
$unread_count = 0;
if ($current_user_id > 0) {
    $unread_query = "
        SELECT COUNT(*) as unread_count 
        FROM support_messages sm
        INNER JOIN support_tickets st ON sm.ticket_id = st.id
        WHERE st.user_id = ? 
        AND sm.is_admin = 1 
        AND sm.is_read = 0
    ";
    $stmt = $conn->prepare($unread_query);
    if ($stmt) {
        $stmt->bind_param("i", $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $unread_data = $result->fetch_assoc();
        $unread_count = $unread_data['unread_count'] ?? 0;
        $stmt->close();
    }
}
?>

<aside class="sidebar">
    <ul>
        <li><a href="My_Orders.php" data-short="Orders">My Orders</a></li>
        <li><a href="Wishlist.php" data-short="Wishlist">Wishlist</a></li>
        <li><a href="Track_Order.php" data-short="track">Track Order</a></li>
        <li><a href="Setting.php" data-short="Setting">Account Settings</a></li>
        
        <!-- Support with Unread Message Badge -->
        <li>
            <a href="support.php" data-short="support" class="support-link">
                Support
                <?php if ($unread_count > 0): ?>
                    <span class="message-badge" title="<?php echo $unread_count; ?> new reply from support">
                        <?php echo $unread_count > 99 ? '99+' : $unread_count; ?>
                    </span>
                <?php endif; ?>
            </a>
        </li>
        
        <li><a href="../logout.php" class="logout-btn">
            Logout
        </a></li>
    </ul>

    <style>
        /* Message Badge Styles */
        .message-badge {
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

        /* Responsive design for mobile */
        @media (max-width: 768px) {
            .message-badge {
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

        .sidebar.collapsed .support-link > span:not(.message-badge) {
            display: none;
        }
    </style>
</aside>