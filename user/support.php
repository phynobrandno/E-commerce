<?php
session_start();
require_once __DIR__ . '/../classes/UserLayout.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
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

$user_id = $_SESSION['user_id'];

// Fetch user's support tickets
$stmt = $conn->prepare("
    SELECT st.*, 
           (SELECT COUNT(*) FROM support_messages WHERE ticket_id = st.id AND is_admin = 1 AND is_read = 0) as unread_count
    FROM support_tickets st 
    WHERE st.user_id = ? 
    ORDER BY st.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$tickets = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle new ticket submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ticket'])) {
    $subject = trim($_POST['subject']);
    $category = $_POST['category'];
    $message = trim($_POST['message']);
    $priority = $_POST['priority'];
    
    if (!empty($subject) && !empty($message)) {
        $stmt = $conn->prepare("INSERT INTO support_tickets (user_id, subject, category, priority, status) VALUES (?, ?, ?, ?, 'open')");
        $stmt->bind_param("isss", $user_id, $subject, $category, $priority);
        
        if ($stmt->execute()) {
            $ticket_id = $conn->insert_id;
            
            // Add first message
            $stmt = $conn->prepare("INSERT INTO support_messages (ticket_id, user_id, message, is_admin) VALUES (?, ?, ?, 0)");
            $stmt->bind_param("iis", $ticket_id, $user_id, $message);
            $stmt->execute();
            $stmt->close();
            
            header("Location: support.php?ticket_id=" . $ticket_id);
            exit();
        }
        $stmt->close();
    }
}

// Handle sending messages
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $ticket_id = (int)$_POST['ticket_id'];
    $message = trim($_POST['message']);
    
    // Verify ticket belongs to user
    $stmt = $conn->prepare("SELECT id FROM support_tickets WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $ticket_id, $user_id);
    $stmt->execute();
    $check_result = $stmt->get_result();
    
    if ($check_result->num_rows > 0 && !empty($message)) {
        $stmt = $conn->prepare("INSERT INTO support_messages (ticket_id, user_id, message, is_admin) VALUES (?, ?, ?, 0)");
        $stmt->bind_param("iis", $ticket_id, $user_id, $message);
        $stmt->execute();
        $stmt->close();
        
        // Update ticket status
        $stmt = $conn->prepare("UPDATE support_tickets SET status = 'open', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->bind_param("i", $ticket_id);
        $stmt->execute();
        $stmt->close();
        
        header("Location: support.php?ticket_id=" . $ticket_id);
        exit();
    }
    $stmt->close();
}

// Handle ticket deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_ticket'])) {
    $ticket_id = (int)$_POST['ticket_id'];
    
    // Verify ticket belongs to user
    $stmt = $conn->prepare("SELECT id FROM support_tickets WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $ticket_id, $user_id);
    $stmt->execute();
    $check_result = $stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Delete messages first
        $stmt = $conn->prepare("DELETE FROM support_messages WHERE ticket_id = ?");
        $stmt->bind_param("i", $ticket_id);
        $stmt->execute();
        $stmt->close();
        
        // Delete ticket
        $stmt = $conn->prepare("DELETE FROM support_tickets WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $ticket_id, $user_id);
        $stmt->execute();
        $stmt->close();
        
        header("Location: support.php");
        exit();
    }
    $stmt->close();
}

// Get current ticket if viewing one
$current_ticket = null;
$messages = [];
if (isset($_GET['ticket_id'])) {
    $ticket_id = (int)$_GET['ticket_id'];
    
    $stmt = $conn->prepare("SELECT * FROM support_tickets WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $ticket_id, $user_id);
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
        $stmt->bind_param("i", $ticket_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $messages = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Mark admin messages as read
        $stmt = $conn->prepare("UPDATE support_messages SET is_read = 1 WHERE ticket_id = ? AND is_admin = 1");
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
    <title>Customer Support</title>
    <link rel="stylesheet" href="../navbar_sidebar.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, sans-serif;
            background: #fff;
            color: #2c3e50;
        }

        .main-content {
            margin-left: 100px;
            width: calc(100% - 100px);
            padding: 20px;
            margin-top: 60px;
            transition: margin-left 0.35s ease-in-out, width 0.35s ease-in-out;
        }

        .main-content.sidebar-expanded {
            margin-left: 230px;
            width: calc(100% - 230px);
        }

        .support-container {
            width: 100%;
            padding: 20px;
        }

        /* HEADER */
        .support-header {
            background: white;
            padding: 25px 35px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            transition: all 0.35s ease-in-out;
        }

        .support-header h1 {
            font-size: 26px;
            color: #1f2937;
            margin-bottom: 6px;
        }

        .support-header p {
            color: #6b7280;
        }

        /* LAYOUT */
        .support-layout {
            display: grid;
            grid-template-columns: 340px 1fr;
            gap: 25px;
            height: 600px;
            transition: all 0.35s ease-in-out;
        }

        /* SIDEBAR */
        .tickets-sidebar {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 20px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: all 0.35s ease-in-out;
        }

        .new-ticket-btn {
            padding: 14px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            margin-bottom: 20px;
            transition: background 0.3s ease, transform 0.2s;
        }

        .new-ticket-btn:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
        }

        .sidebar-header {
            margin-bottom: 15px;
        }

        .sidebar-header h3 {
            color: #2c3e50;
            font-size: 14px;
            font-weight: 600;
        }

        .tickets-list {
            flex: 1;
            overflow-y: auto;
        }

        .ticket-item {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 10px;
            background: #f9fafb;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s ease;
            position: relative;
        }

        .ticket-item:hover {
            background: #eef2ff;
            border-color: #c7d2fe;
        }

        .ticket-item.active {
            background: #e0e7ff;
            border-color: #6366f1;
        }

        .ticket-title {
            font-weight: 600;
            color: #111827;
            font-size: 13px;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .ticket-meta {
            font-size: 11px;
            color: #6b7280;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .ticket-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-open { background: #dcfce7; color: #166534; }
        .status-pending { background: #fef9c3; color: #92400e; }
        .status-closed { background: #fee2e2; color: #991b1b; }

        .unread-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .empty-tickets {
            color: #7f8c8d;
            text-align: center;
            padding: 20px;
            font-size: 13px;
        }

        /* MAIN CHAT PANEL */
        .ticket-content {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 25px 30px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: all 0.35s ease-in-out;
        }

        .ticket-header {
            margin-bottom: 20px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 20px;
        }

        .ticket-header h2 {
            font-size: 20px;
            color: #111827;
            margin-bottom: 10px;
        }

        .ticket-info {
            display: flex;
            gap: 15px;
            color: #7f8c8d;
            font-size: 13px;
            flex-wrap: wrap;
        }

        .ticket-info strong {
            color: #111827;
        }

        /* CHAT */
        .chat-container {
            flex: 1;
            overflow-y: auto;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            background: #f9fafb;
            margin-bottom: 15px;
        }

        .message {
            display: flex;
            margin-bottom: 15px;
        }

        .message.user-msg {
            justify-content: flex-end;
        }

        .message-content {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 10px;
            line-height: 1.4;
            word-wrap: break-word;
            font-size: 13px;
        }

        .message.admin-msg .message-content {
            background: white;
            border: 1px solid #e5e7eb;
            color: #111827;
        }

        .message.user-msg .message-content {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
        }

        .message-author {
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 4px;
            opacity: 0.8;
        }

        .message-time {
            font-size: 10px;
            opacity: 0.6;
            margin-top: 4px;
        }

        /* REPLY FORM */
        .reply-form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .reply-input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 13px;
            resize: vertical;
            min-height: 70px;
            max-height: 100px;
        }

        .reply-input:focus {
            border-color: #6366f1;
            outline: none;
        }

        .send-btn {
            align-self: flex-end;
            padding: 10px 25px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            transition: transform 0.2s ease;
        }

        .send-btn:hover {
            transform: translateY(-2px);
        }

        /* EMPTY STATE */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }

        .empty-state-icon {
            font-size: 50px;
            margin-bottom: 15px;
        }

        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 8px;
            color: #111827;
        }

        .empty-state p {
            font-size: 13px;
        }

        .ticket-closed-msg {
            text-align: center;
            padding: 15px;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 10px;
        }

        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }

        /* MODAL */
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
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
            color: #111827;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6b7280;
            transition: color 0.2s;
        }

        .close-modal:hover {
            color: #111827;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #111827;
            font-size: 14px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #6366f1;
            outline: none;
        }

        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }

        .modal-submit-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .modal-submit-btn:hover {
            transform: translateY(-2px);
        }

        .delete-btn {
            padding: 10px 16px;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            transition: background 0.2s;
        }

        .delete-btn:hover {
            background: #dc2626;
        }

        .ticket-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
        }

        .delete-confirm-modal {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .delete-confirm-modal.active {
            display: flex;
        }

        .delete-confirm-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.15);
        }

        .delete-confirm-icon {
            font-size: 40px;
            margin-bottom: 15px;
        }

        .delete-confirm-content h3 {
            font-size: 18px;
            color: #111827;
            margin-bottom: 10px;
        }

        .delete-confirm-content p {
            color: #6b7280;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .confirm-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .confirm-cancel-btn {
            padding: 10px 20px;
            background: #e5e7eb;
            color: #111827;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.2s;
        }

        .confirm-cancel-btn:hover {
            background: #d1d5db;
        }

        .confirm-delete-btn {
            padding: 10px 20px;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.2s;
        }

        .confirm-delete-btn:hover {
            background: #dc2626;
        }

        @media (max-width: 1024px) {
            .support-layout {
                grid-template-columns: 300px 1fr;
                height: 500px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 20px;
                margin-top: 50px;
            }

            .main-content.sidebar-expanded {
                margin-left: 200px;
                width: calc(100% - 200px);
            }

            .support-layout {
                grid-template-columns: 1fr;
                height: auto;
            }

            .tickets-sidebar {
                max-height: 300px;
            }

            .support-container {
                margin-top: 70px;
            }
        }
    </style>
</head>
<body>

<?php UserLayout::navbar(); ?>
<?php UserLayout::sidebar(); ?>

<div class="main-content">
    <div class="support-container">
        <div class="support-header">
            <h1>💬 Customer Support</h1>
            <p>Get help with your orders, account, or any other questions</p>
        </div>

        <div class="support-layout">
            <div class="tickets-sidebar">
                <button class="new-ticket-btn" onclick="openNewTicketModal()">+ New Support Ticket</button>
                
                <div class="sidebar-header">
                    <h3>My Tickets</h3>
                </div>

                <div class="tickets-list">
                    <?php if (empty($tickets)): ?>
                        <div class="empty-tickets">No tickets yet. Create one to get started!</div>
                    <?php else: ?>
                        <?php foreach ($tickets as $ticket): ?>
                            <div class="ticket-item <?php echo ($current_ticket && $current_ticket['id'] == $ticket['id']) ? 'active' : ''; ?>" 
                                 onclick="window.location.href='support.php?ticket_id=<?php echo $ticket['id']; ?>'">
                                <?php if ($ticket['unread_count'] > 0): ?>
                                    <span class="unread-badge"><?php echo $ticket['unread_count']; ?></span>
                                <?php endif; ?>
                                <div class="ticket-title"><?php echo htmlspecialchars(substr($ticket['subject'], 0, 30)); ?></div>
                                <div class="ticket-meta">
                                    <span class="ticket-status status-<?php echo htmlspecialchars($ticket['status']); ?>">
                                        <?php echo htmlspecialchars($ticket['status']); ?>
                                    </span>
                                    <span><?php echo date('M d', strtotime($ticket['created_at'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ticket-content">
                <?php if ($current_ticket): ?>
                    <div class="ticket-header">
                        <h2><?php echo htmlspecialchars($current_ticket['subject']); ?></h2>
                        <div class="ticket-info">
                            <span>Category: <strong><?php echo htmlspecialchars($current_ticket['category']); ?></strong></span>
                            <span>Priority: <strong><?php echo htmlspecialchars($current_ticket['priority']); ?></strong></span>
                            <span>Status: <span class="ticket-status status-<?php echo htmlspecialchars($current_ticket['status']); ?>"><?php echo htmlspecialchars($current_ticket['status']); ?></span></span>
                        </div>
                        <div class="ticket-actions">
                            <button type="button" class="delete-btn" onclick="openDeleteConfirm()">🗑️ Delete Ticket</button>
                        </div>
                    </div>

                    <div class="chat-container">
                        <?php foreach ($messages as $msg): ?>
                            <div class="message <?php echo $msg['is_admin'] ? 'admin-msg' : 'user-msg'; ?>">
                                <div class="message-content">
                                    <div class="message-author">
                                        <?php echo $msg['is_admin'] ? '👨‍💼 Support Team' : '👤 You'; ?>
                                    </div>
                                    <div><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                                    <div class="message-time">
                                        <?php echo date('M d, H:i', strtotime($msg['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($current_ticket['status'] !== 'closed'): ?>
                        <form method="POST" class="reply-form">
                            <input type="hidden" name="ticket_id" value="<?php echo $current_ticket['id']; ?>">
                            <textarea name="message" class="reply-input" placeholder="Type your message..." required></textarea>
                            <button type="submit" name="send_message" class="send-btn">Send Message</button>
                        </form>
                    <?php else: ?>
                        <div class="ticket-closed-msg">
                            🔒 This ticket is closed. Please create a new ticket if you need further assistance.
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">💬</div>
                        <h3>No Ticket Selected</h3>
                        <p>Select a ticket from the sidebar or create a new one to get started</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- New Ticket Modal -->
<div class="modal" id="newTicketModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Create New Support Ticket</h2>
            <button class="close-modal" onclick="closeNewTicketModal()">&times;</button>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label>Subject *</label>
                <input type="text" name="subject" required placeholder="Brief description of your issue">
            </div>
            
            <div class="form-group">
                <label>Category *</label>
                <select name="category" required>
                    <option value="">-- Select a category --</option>
                    <option value="Order Issue">Order Issue</option>
                    <option value="Payment">Payment</option>
                    <option value="Shipping">Shipping</option>
                    <option value="Product Question">Product Question</option>
                    <option value="Account">Account</option>
                    <option value="Technical">Technical</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Priority *</label>
                <select name="priority" required>
                    <option value="low">Low</option>
                    <option value="medium" selected>Medium</option>
                    <option value="high">High</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Message *</label>
                <textarea name="message" required placeholder="Describe your issue in detail..."></textarea>
            </div>
            
            <button type="submit" name="create_ticket" class="modal-submit-btn">Create Ticket</button>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="delete-confirm-modal" id="deleteConfirmModal">
    <div class="delete-confirm-content">
        <div class="delete-confirm-icon">⚠️</div>
        <h3>Delete Ticket?</h3>
        <p>Are you sure you want to delete this ticket? This action cannot be undone and all messages will be permanently deleted.</p>
        <div class="confirm-actions">
            <button class="confirm-cancel-btn" onclick="closeDeleteConfirm()">Cancel</button>
            <button class="confirm-delete-btn" onclick="confirmDelete()">Delete Ticket</button>
        </div>
    </div>
</div>

<!-- Hidden delete form -->
<form method="POST" id="deleteForm" style="display: none;">
    <?php if ($current_ticket): ?>
        <input type="hidden" name="ticket_id" value="<?php echo $current_ticket['id']; ?>">
    <?php endif; ?>
    <input type="hidden" name="delete_ticket" value="1">
</form>

<script>
    function openNewTicketModal() {
        document.getElementById('newTicketModal').classList.add('active');
    }

    function closeNewTicketModal() {
        document.getElementById('newTicketModal').classList.remove('active');
    }

    function openDeleteConfirm() {
        document.getElementById('deleteConfirmModal').classList.add('active');
    }

    function closeDeleteConfirm() {
        document.getElementById('deleteConfirmModal').classList.remove('active');
    }

    function confirmDelete() {
        const form = document.getElementById('deleteForm');
        form.submit();
    }

    // Detect sidebar hover/expansion and adjust content
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');

        if (sidebar && mainContent) {
            sidebar.addEventListener('mouseenter', function() {
                mainContent.classList.add('sidebar-expanded');
            });
            
            sidebar.addEventListener('mouseleave', function() {
                mainContent.classList.remove('sidebar-expanded');
            });
        }
    });

    // Auto-scroll chat to bottom
    const chatContainer = document.querySelector('.chat-container');
    if (chatContainer) {
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }

    // Close modal on outside click
    document.getElementById('newTicketModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeNewTicketModal();
        }
    });

    document.getElementById('deleteConfirmModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDeleteConfirm();
        }
    });
</script>

</body>
</html>