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

$admin_id = $_SESSION['user_id'];

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $ticket_id = (int)$_POST['ticket_id'];
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE support_tickets SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->bind_param("si", $status, $ticket_id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: Customer_Support.php?ticket_id=" . $ticket_id);
    exit();
}

// Handle admin reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $ticket_id = (int)$_POST['ticket_id'];
    $message = trim($_POST['message']);
    
    if (!empty($message)) {
        $stmt = $conn->prepare("INSERT INTO support_messages (ticket_id, user_id, message, is_admin) VALUES (?, ?, ?, 1)");
        $stmt->bind_param("iis", $ticket_id, $admin_id, $message);
        $stmt->execute();
        $stmt->close();
        
        // Update ticket timestamp
        $stmt = $conn->prepare("UPDATE support_tickets SET status = 'pending', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->bind_param("i", $ticket_id);
        $stmt->execute();
        $stmt->close();
        
        header("Location: Customer_Support.php?ticket_id=" . $ticket_id);
        exit();
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$priority_filter = isset($_GET['priority']) ? $_GET['priority'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query using prepared statements
$query = "
    SELECT st.*, u.username, u.email,
           (SELECT COUNT(*) FROM support_messages WHERE ticket_id = st.id AND is_admin = 0 AND is_read = 0) as unread_count
    FROM support_tickets st 
    LEFT JOIN users u ON st.user_id = u.id 
    WHERE 1=1
";

$params = [];
$types = '';

if ($status_filter !== 'all') {
    $query .= " AND st.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}
if ($priority_filter !== 'all') {
    $query .= " AND st.priority = ?";
    $params[] = $priority_filter;
    $types .= 's';
}
if (!empty($search)) {
    $search_term = '%' . $search . '%';
    $query .= " AND (st.subject LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'sss';
}

$query .= " ORDER BY st.updated_at DESC";

// Execute main query
$tickets = [];
if (!empty($params)) {
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $tickets = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $result = $conn->query($query);
    if ($result === false) {
        die("Query error: " . $conn->error);
    }
    $tickets = $result->fetch_all(MYSQLI_ASSOC);
}

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as `open`,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as `pending`,
        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as `closed`,
        SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as `high_priority`
    FROM support_tickets
";
$result = $conn->query($stats_query);
if ($result === false) {
    die("Stats query error: " . $conn->error);
}
$stats = $result->fetch_assoc();
if ($stats === null) {
    $stats = ['total' => 0, 'open' => 0, 'pending' => 0, 'closed' => 0, 'high_priority' => 0];
}

// Get current ticket if viewing one
$current_ticket = null;
$messages = [];
if (isset($_GET['ticket_id'])) {
    $ticket_id = (int)$_GET['ticket_id'];
    
    $stmt = $conn->prepare("
        SELECT st.*, u.username, u.email 
        FROM support_tickets st 
        LEFT JOIN users u ON st.user_id = u.id 
        WHERE st.id = ?
    ");
    if ($stmt === false) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $ticket_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_ticket = $result->fetch_assoc();
    $stmt->close();
    
    if ($current_ticket) {
        // Get messages
        $stmt = $conn->prepare("
            SELECT sm.*, u.username, u.role 
            FROM support_messages sm 
            LEFT JOIN users u ON sm.user_id = u.id 
            WHERE sm.ticket_id = ? 
            ORDER BY sm.created_at ASC
        ");
        if ($stmt === false) {
            die("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("i", $ticket_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $messages = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Mark user messages as read
        $stmt = $conn->prepare("UPDATE support_messages SET is_read = 1 WHERE ticket_id = ? AND is_admin = 0");
        if ($stmt === false) {
            die("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("i", $ticket_id);
        $stmt->execute();
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Support Management</title>
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
            padding: 30px 20px;
            margin-top: 60px;
            transition: margin-left 0.35s ease-in-out, width 0.35s ease-in-out;
        }

        .content.sidebar-expanded {
            margin-left: 230px;
            width: calc(100% - 230px);
        }

        .support-container {
            max-width: 1600px;
            margin: 0 auto;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            color: #2c3e50;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #7f8c8d;
            font-size: 14px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
            transition: all 0.35s ease-in-out;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #667eea;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }

        .stat-card.open {
            border-left-color: #2ecc71;
        }

        .stat-card.pending {
            border-left-color: #f39c12;
        }

        .stat-card.closed {
            border-left-color: #e74c3c;
        }

        .stat-card.high {
            border-left-color: #e74c3c;
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
            font-size: 28px;
            font-weight: 700;
            color: #2c3e50;
        }

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
            letter-spacing: 0.5px;
        }

        .filter-group select,
        .filter-group input {
            padding: 10px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            font-family: inherit;
            transition: border-color 0.3s;
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
            display: inline-block;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #ecf0f1;
            color: #2c3e50;
        }

        .btn-secondary:hover {
            background: #dde0e3;
        }

        .main-layout {
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 25px;
            height: 650px;
            transition: all 0.35s ease-in-out;
        }

        .sidebar-section {
            display: flex;
            flex-direction: column;
            gap: 0;
            height: 100%;
        }

        .sidebar-header {
            background: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .sidebar-header h3 {
            color: #2c3e50;
            font-size: 16px;
            font-weight: 600;
        }

        .ticket-badge {
            background: #667eea;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .tickets-list {
            background: white;
            flex: 1;
            overflow-y: auto;
            padding: 15px;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-bottom: 1px solid #f0f0f0;
        }

        .ticket-item {
            padding: 12px;
            margin-bottom: 10px;
            border: 1px solid #e8e8e8;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            background: white;
        }

        .ticket-item:hover {
            border-color: #667eea;
            background: #f8f9ff;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.15);
        }

        .ticket-item.active {
            border-color: #667eea;
            background: #f0f2ff;
        }

        .ticket-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 6px;
            gap: 8px;
        }

        .ticket-subject {
            font-weight: 600;
            color: #2c3e50;
            font-size: 13px;
            flex: 1;
            word-break: break-word;
        }

        .ticket-priority {
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .priority-high {
            background: #ffe0e0;
            color: #c41e3a;
        }

        .priority-medium {
            background: #fff3cd;
            color: #856404;
        }

        .priority-low {
            background: #d4edda;
            color: #155724;
        }

        .ticket-user {
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 4px;
        }

        .ticket-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
            gap: 8px;
        }

        .ticket-status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-open {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-closed {
            background: #f8d7da;
            color: #721c24;
        }

        .ticket-time {
            color: #95a5a6;
        }

        .unread-indicator {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 8px;
            height: 8px;
            background: #e74c3c;
            border-radius: 50%;
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

        .content-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .ticket-header {
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
        }

        .ticket-title {
            font-size: 18px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 12px;
        }

        .ticket-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            font-size: 13px;
        }

        .detail-item strong {
            color: #2c3e50;
            display: block;
            margin-bottom: 3px;
        }

        .detail-item {
            color: #7f8c8d;
        }

        .ticket-controls {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }

        .status-control {
            display: flex;
            gap: 8px;
            align-items: center;
            flex: 1;
        }

        .status-control select {
            padding: 8px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
        }

        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .message {
            display: flex;
            animation: slideIn 0.3s ease;
            max-width: 85%;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.user {
            justify-content: flex-start;
        }

        .message.admin {
            justify-content: flex-end;
            align-self: flex-end;
        }

        .message-bubble {
            padding: 12px 15px;
            border-radius: 8px;
            word-wrap: break-word;
            font-size: 13px;
            line-height: 1.4;
        }

        .message.user .message-bubble {
            background: #f0f0f0;
            color: #2c3e50;
            border: 1px solid #e0e0e0;
        }

        .message.admin .message-bubble {
            background: #667eea;
            color: white;
        }

        .message-info {
            font-size: 11px;
            color: #95a5a6;
            margin-top: 4px;
            padding: 0 10px;
        }

        .reply-section {
            padding: 15px 20px;
            border-top: 1px solid #f0f0f0;
            background: #fafafa;
        }

        .reply-form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .reply-input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 13px;
            font-family: inherit;
            resize: vertical;
            min-height: 80px;
            max-height: 120px;
            transition: border-color 0.3s;
        }

        .reply-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
        }

        .send-btn {
            padding: 10px 25px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s;
        }

        .send-btn:hover {
            background: #5568d3;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .no-selection {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #7f8c8d;
            font-size: 14px;
        }

        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: #d0d0d0;
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #b0b0b0;
        }

        @media (max-width: 1200px) {
            .main-layout {
                grid-template-columns: 1fr;
                height: auto;
            }

            .sidebar-section {
                height: 400px;
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

            .main-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<?php Layout::navbar(); ?>
<?php Layout::sidebar(); ?>

<div class="content">
    <div class="support-container">
        <div class="page-header">
            <h1>📞 Customer Support</h1>
            <p>Manage and respond to customer support tickets</p>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Tickets</div>
                <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
            </div>
            <div class="stat-card open">
                <div class="stat-label">Open</div>
                <div class="stat-number"><?php echo $stats['open'] ?? 0; ?></div>
            </div>
            <div class="stat-card pending">
                <div class="stat-label">Pending</div>
                <div class="stat-number"><?php echo $stats['pending'] ?? 0; ?></div>
            </div>
            <div class="stat-card closed">
                <div class="stat-label">Closed</div>
                <div class="stat-number"><?php echo $stats['closed'] ?? 0; ?></div>
            </div>
            <div class="stat-card high">
                <div class="stat-label">High Priority</div>
                <div class="stat-number"><?php echo $stats['high_priority'] ?? 0; ?></div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <div class="filter-content">
                <div class="filter-group">
                    <label>Status</label>
                    <select id="statusFilter" onchange="applyFilters()">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="open" <?php echo $status_filter == 'open' ? 'selected' : ''; ?>>Open</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="closed" <?php echo $status_filter == 'closed' ? 'selected' : ''; ?>>Closed</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Priority</label>
                    <select id="priorityFilter" onchange="applyFilters()">
                        <option value="all" <?php echo $priority_filter == 'all' ? 'selected' : ''; ?>>All Priorities</option>
                        <option value="high" <?php echo $priority_filter == 'high' ? 'selected' : ''; ?>>High</option>
                        <option value="medium" <?php echo $priority_filter == 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="low" <?php echo $priority_filter == 'low' ? 'selected' : ''; ?>>Low</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" id="searchInput" placeholder="Search tickets..." value="<?php echo htmlspecialchars($search); ?>" onkeyup="applyFilters()">
                </div>

                <button class="btn btn-secondary" onclick="window.location.href='Customer_Support.php'">Clear All</button>
            </div>
        </div>

        <!-- Main Layout -->
        <div class="main-layout">
            <!-- Sidebar -->
            <div class="sidebar-section">
                <div class="sidebar-header">
                    <h3>Tickets</h3>
                    <span class="ticket-badge"><?php echo count($tickets); ?></span>
                </div>
                <div class="tickets-list">
                    <?php if (empty($tickets)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">📭</div>
                            <p>No tickets found</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($tickets as $ticket): ?>
                            <div class="ticket-item <?php echo ($current_ticket && $current_ticket['id'] == $ticket['id']) ? 'active' : ''; ?>" 
                                 onclick="window.location.href='Customer_Support.php?ticket_id=<?php echo $ticket['id']; ?>&status=<?php echo urlencode($status_filter); ?>&priority=<?php echo urlencode($priority_filter); ?>&search=<?php echo urlencode($search); ?>'">
                                
                                <?php if ($ticket['unread_count'] > 0): ?>
                                    <div class="unread-indicator"></div>
                                <?php endif; ?>
                                
                                <div class="ticket-top">
                                    <div class="ticket-subject"><?php echo htmlspecialchars(substr($ticket['subject'], 0, 40)); ?></div>
                                    <span class="ticket-priority priority-<?php echo htmlspecialchars($ticket['priority']); ?>">
                                        <?php echo substr($ticket['priority'], 0, 1); ?>
                                    </span>
                                </div>
                                
                                <div class="ticket-user">👤 <?php echo htmlspecialchars(substr($ticket['username'] ?? 'Unknown', 0, 20)); ?></div>
                                
                                <div class="ticket-meta">
                                    <span class="ticket-status status-<?php echo htmlspecialchars($ticket['status']); ?>">
                                        <?php echo htmlspecialchars($ticket['status']); ?>
                                    </span>
                                    <span class="ticket-time"><?php echo date('M d', strtotime($ticket['updated_at'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Content -->
            <div class="content-section">
                <?php if ($current_ticket): ?>
                    <div class="ticket-header">
                        <div class="ticket-title"><?php echo htmlspecialchars($current_ticket['subject']); ?></div>
                        <div class="ticket-details">
                            <div class="detail-item">
                                <strong>From:</strong> <?php echo htmlspecialchars($current_ticket['username'] ?? 'Unknown'); ?>
                            </div>
                            <div class="detail-item">
                                <strong>Email:</strong> <?php echo htmlspecialchars($current_ticket['email'] ?? 'N/A'); ?>
                            </div>
                            <div class="detail-item">
                                <strong>Category:</strong> <?php echo htmlspecialchars($current_ticket['category']); ?>
                            </div>
                            <div class="detail-item">
                                <strong>Priority:</strong> 
                                <span class="ticket-priority priority-<?php echo htmlspecialchars($current_ticket['priority']); ?>">
                                    <?php echo htmlspecialchars($current_ticket['priority']); ?>
                                </span>
                            </div>
                            <div class="detail-item">
                                <strong>Created:</strong> <?php echo date('M d, Y', strtotime($current_ticket['created_at'])); ?>
                            </div>
                            <div class="detail-item">
                                <strong>Updated:</strong> <?php echo date('M d, Y', strtotime($current_ticket['updated_at'])); ?>
                            </div>
                        </div>

                        <div class="ticket-controls">
                            <form method="POST" class="status-control">
                                <input type="hidden" name="ticket_id" value="<?php echo $current_ticket['id']; ?>">
                                <select name="status">
                                    <option value="open" <?php echo $current_ticket['status'] == 'open' ? 'selected' : ''; ?>>Open</option>
                                    <option value="pending" <?php echo $current_ticket['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="closed" <?php echo $current_ticket['status'] == 'closed' ? 'selected' : ''; ?>>Closed</option>
                                </select>
                                <button type="submit" name="update_status" class="btn btn-primary" style="padding: 8px 16px; font-size: 12px;">Update</button>
                            </form>
                        </div>
                    </div>

                    <div class="chat-area">
                        <div class="messages-container">
                            <?php foreach ($messages as $msg): ?>
                                <div class="message <?php echo $msg['is_admin'] ? 'admin' : 'user'; ?>">
                                    <div>
                                        <div class="message-bubble">
                                            <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                        </div>
                                        <div class="message-info">
                                            <strong><?php echo htmlspecialchars($msg['username']); ?></strong> <?php echo $msg['is_admin'] ? '(Support)' : ''; ?> • <?php echo date('M d, H:i', strtotime($msg['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="reply-section">
                            <form method="POST" class="reply-form">
                                <input type="hidden" name="ticket_id" value="<?php echo $current_ticket['id']; ?>">
                                <textarea name="message" class="reply-input" placeholder="Type your reply here..." required></textarea>
                                <div class="form-actions">
                                    <button type="submit" name="send_reply" class="send-btn">Send Reply</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-selection">
                        <div style="text-align: center;">
                            <div style="font-size: 48px; margin-bottom: 15px;">💬</div>
                            <h3 style="color: #2c3e50; margin-bottom: 5px;">Select a Ticket</h3>
                            <p>Choose a ticket from the list to view details and respond</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    function applyFilters() {
        const status = document.getElementById('statusFilter').value;
        const priority = document.getElementById('priorityFilter').value;
        const search = document.getElementById('searchInput').value;
        
        window.location.href = `Customer_Support.php?status=${status}&priority=${priority}&search=${encodeURIComponent(search)}`;
    }

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

    // Auto-scroll messages to bottom
    const messagesContainer = document.querySelector('.messages-container');
    if (messagesContainer) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
</script>

</body>
</html>