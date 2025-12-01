<?php
// verify-payment.php - Admin verification page
require_once __DIR__ . '/../config/security.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../classes/db_connect.php';
require_once __DIR__ . '/../classes/Layout.php';


// Protect - only admins
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$payment_id = $_GET['payment_id'] ?? null;
$success_message = null;
$error_message = null;

// Handle approval or rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    $payment_id = $_POST['payment_id'] ?? null;
    
    if ($action && $payment_id) {
        $conn->begin_transaction();
        
        try {
            // Fetch payment and order details
            $stmt = $conn->prepare("SELECT p.id, p.order_id, p.user_id, o.total_amount FROM payments p 
                                   JOIN orders o ON p.order_id = o.id WHERE p.id = ?");
            $stmt->bind_param("i", $payment_id);
            $stmt->execute();
            $payment = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if (!$payment) {
                throw new Exception("Payment not found");
            }
            
            $order_id = $payment['order_id'];
            $user_id = $payment['user_id'];
            $amount = $payment['total_amount'];
            
            if ($action === 'approve') {
                // Update order status to completed
                $update_order = $conn->prepare("UPDATE orders SET status = 'completed' WHERE id = ?");
                $update_order->bind_param("i", $order_id);
                $update_order->execute();
                $update_order->close();
                
                // Update payment status to completed
                $update_payment = $conn->prepare("UPDATE payments SET status = 'completed' WHERE id = ?");
                $update_payment->bind_param("i", $payment_id);
                $update_payment->execute();
                $update_payment->close();
                
                // Get user info for notification
                $user_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
                $user_stmt->bind_param("i", $user_id);
                $user_stmt->execute();
                $user_info = $user_stmt->get_result()->fetch_assoc();
                $user_stmt->close();
                
                // Create notification for user
                $notif_stmt = $conn->prepare("INSERT INTO user_notifications (user_id, title, message, type, is_read) VALUES (?, ?, ?, 'success', 0)");
                $title = "✅ Payment Approved!";
                $message = "Your payment for Order #" . $order_id . " has been verified and approved! Your order will be processed shortly.";
                $notif_stmt->bind_param("iss", $user_id, $title, $message);
                $notif_stmt->execute();
                $notif_stmt->close();
                
                $conn->commit();
                $success_message = "✅ Payment approved successfully! User has been notified.";
                
            } elseif ($action === 'reject') {
                $reason = $_POST['reason'] ?? 'Payment could not be verified';
                
                // Update order status to cancelled
                $update_order = $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
                $update_order->bind_param("i", $order_id);
                $update_order->execute();
                $update_order->close();
                
                // Update payment status to cancelled
                $update_payment = $conn->prepare("UPDATE payments SET status = 'cancelled' WHERE id = ?");
                $update_payment->bind_param("i", $payment_id);
                $update_payment->execute();
                $update_payment->close();
                
                // Get user info for notification
                $user_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
                $user_stmt->bind_param("i", $user_id);
                $user_stmt->execute();
                $user_info = $user_stmt->get_result()->fetch_assoc();
                $user_stmt->close();
                
                // Create notification for user
                $notif_stmt = $conn->prepare("INSERT INTO user_notifications (user_id, title, message, type, is_read) VALUES (?, ?, ?, 'danger', 0)");
                $title = "❌ Payment Could Not Be Verified";
                $message = "Your payment for Order #" . $order_id . " could not be verified. Reason: " . $reason . " Please contact support.";
                $notif_stmt->bind_param("iss", $user_id, $title, $message);
                $notif_stmt->execute();
                $notif_stmt->close();
                
                $conn->commit();
                $success_message = "❌ Payment rejected. User has been notified.";
            }
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Fetch payment details if payment_id provided
$payment_data = null;
if ($payment_id) {
    $stmt = $conn->prepare("SELECT p.id, p.order_id, p.user_id, p.payment_method, p.amount, p.receipt_image, 
                                   p.transaction_id, p.created_at, p.status,
                                   u.username, u.email,
                                   o.delivery_address, o.phone, o.total_amount
                            FROM payments p
                            JOIN users u ON p.user_id = u.id
                            JOIN orders o ON p.order_id = o.id
                            WHERE p.id = ?");
    $stmt->bind_param("i", $payment_id);
    $stmt->execute();
    $payment_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Fetch all pending payments
$pending_query = $conn->query("
    SELECT p.id, p.order_id, p.user_id, p.amount, p.payment_method, p.receipt_image, 
           p.transaction_id, p.created_at, p.status,
           u.username, u.email
    FROM payments p
    JOIN users u ON p.user_id = u.id
    WHERE p.status = 'pending'
    ORDER BY p.created_at DESC
");

$pending_payments = [];
while ($row = $pending_query->fetch_assoc()) {
    $pending_payments[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Payment - Admin</title>
    <link rel="stylesheet" href="../navbar_sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #fff; }
        .main-content { padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; }
        h1 { color: #333; margin-bottom: 30px; font-size: 32px; }
        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; font-weight: 500; }
        .alert-success { background: #f0fdf4; color: #166534; border-left: 4px solid #22c55e; }
        .alert-error { background: #fff5f5; color: #c53030; border-left: 4px solid #f56565; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        .section { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .section h2 { color: #333; margin-bottom: 20px; font-size: 22px; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; }
        .payment-list { list-style: none; }
        .payment-item { background: #f9f9f9; border: 2px solid #e0e0e0; border-radius: 12px; padding: 15px; margin-bottom: 15px; cursor: pointer; transition: all 0.3s; }
        .payment-item:hover { border-color: #667eea; background: white; box-shadow: 0 4px 12px rgba(102,126,234,0.2); }
        .payment-item.selected { border-color: #667eea; background: #f8f9ff; box-shadow: 0 4px 12px rgba(102,126,234,0.3); }
        .payment-user { font-weight: 700; color: #333; }
        .payment-info { color: #999; font-size: 12px; margin-top: 5px; }
        .payment-amount { color: #667eea; font-weight: 700; font-size: 16px; }
        .badge { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-pending { background: #fef3c7; color: #92400e; }

        /* Detail View */
        .detail-card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .detail-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 25px; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; }
        .detail-header h2 { margin: 0; color: #333; }
        .detail-body { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        .info-section h3 { color: #333; margin-bottom: 15px; font-size: 16px; font-weight: 700; }
        .info-line { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
        .info-line:last-child { border-bottom: none; }
        .info-label { font-weight: 600; color: #666; }
        .info-value { color: #333; text-align: right; }
        .receipt-preview { background: #f9f9f9; border: 2px solid #e0e0e0; border-radius: 12px; padding: 20px; text-align: center; }
        .receipt-image { max-width: 100%; max-height: 400px; border-radius: 10px; cursor: pointer; }
        .receipt-link { color: #667eea; text-decoration: none; font-weight: 600; display: inline-block; margin-top: 10px; }
        .receipt-link:hover { text-decoration: underline; }
        
        /* Address Section */
        .address-box { background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%); border: 2px solid #667eea; border-radius: 12px; padding: 20px; }
        .address-box h3 { color: #667eea; margin-bottom: 10px; font-size: 16px; font-weight: 700; }
        .address-text { color: #333; line-height: 1.8; }

        /* Action Buttons */
        .action-buttons { display: flex; gap: 15px; margin-top: 25px; }
        .btn { padding: 12px 20px; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.3s; flex: 1; }
        .btn-approve { background: #22c55e; color: white; }
        .btn-approve:hover { background: #16a34a; transform: translateY(-2px); }
        .btn-reject { background: #ef4444; color: white; }
        .btn-reject:hover { background: #dc2626; transform: translateY(-2px); }
        
        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; padding: 20px; }
        .modal.active { display: flex; align-items: center; justify-content: center; }
        .modal-content { background: white; border-radius: 15px; padding: 30px; max-width: 400px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        .modal-content h3 { color: #333; margin-bottom: 15px; }
        .modal-content textarea { width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; min-height: 100px; margin-bottom: 15px; }
        .modal-buttons { display: flex; gap: 10px; }
        .modal-buttons button { flex: 1; padding: 12px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .modal-cancel { background: #6c757d; color: white; }
        .modal-confirm { background: #ef4444; color: white; }
        
        .empty-state { text-align: center; padding: 40px; color: #999; }
        .no-payment-selected { text-align: center; padding: 40px; color: #999; }
    </style>
</head>
<body>

<?php Layout::navbar(); ?>
<?php Layout::sidebar(); ?>

<div class="main-content">
    <div class="container">
        <h1>💳 Verify Payment Receipts</h1>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?= $success_message; ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error"><?= $error_message; ?></div>
        <?php endif; ?>

        <div class="grid">
            <!-- Left: Pending Payments List -->
            <div class="section">
                <h2>⏳ Pending Payments (<?= count($pending_payments); ?>)</h2>
                
                <?php if (empty($pending_payments)): ?>
                    <div class="empty-state">
                        <div style="font-size: 50px; margin-bottom: 15px;">✅</div>
                        <p>All payments verified! No pending payments.</p>
                    </div>
                <?php else: ?>
                    <ul class="payment-list">
                        <?php foreach ($pending_payments as $payment): ?>
                            <li class="payment-item <?= ($payment_data && $payment_data['id'] == $payment['id']) ? 'selected' : ''; ?>" 
                                onclick="window.location.href='?payment_id=<?= $payment['id']; ?>'">
                                <div class="payment-user">👤 <?= htmlspecialchars($payment['username']); ?></div>
                                <div class="payment-info">
                                    📧 <?= htmlspecialchars($payment['email']); ?><br>
                                    Order #<?= $payment['order_id']; ?> | <?= date('M d, Y H:i', strtotime($payment['created_at'])); ?>
                                </div>
                                <div style="margin-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                                    <span class="payment-amount">$<?= number_format($payment['amount'], 2); ?></span>
                                    <span class="badge badge-pending">⏳ PENDING</span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <!-- Right: Payment Details -->
            <div class="section">
                <?php if ($payment_data): ?>
                    <div class="detail-card">
                        <div class="detail-header">
                            <h2>Payment Details</h2>
                            <span class="badge badge-pending">⏳ PENDING</span>
                        </div>

                        <div class="detail-body">
                            <!-- Customer Information -->
                            <div>
                                <div class="info-section">
                                    <h3>👤 Customer Information</h3>
                                    <div class="info-line">
                                        <span class="info-label">Name:</span>
                                        <span class="info-value"><?= htmlspecialchars($payment_data['username']); ?></span>
                                    </div>
                                    <div class="info-line">
                                        <span class="info-label">Email:</span>
                                        <span class="info-value"><?= htmlspecialchars($payment_data['email']); ?></span>
                                    </div>
                                    <div class="info-line">
                                        <span class="info-label">Phone:</span>
                                        <span class="info-value"><?= htmlspecialchars($payment_data['phone']); ?></span>
                                    </div>
                                </div>

                                <div class="info-section" style="margin-top: 20px;">
                                    <h3>💳 Payment Information</h3>
                                    <div class="info-line">
                                        <span class="info-label">Order ID:</span>
                                        <span class="info-value">#<?= $payment_data['order_id']; ?></span>
                                    </div>
                                    <div class="info-line">
                                        <span class="info-label">Amount:</span>
                                        <span class="info-value" style="color: #667eea; font-weight: 700;">$<?= number_format($payment_data['amount'], 2); ?></span>
                                    </div>
                                    <div class="info-line">
                                        <span class="info-label">Method:</span>
                                        <span class="info-value"><?= ucfirst(str_replace('_', ' ', $payment_data['payment_method'])); ?></span>
                                    </div>
                                    <div class="info-line">
                                        <span class="info-label">Transaction ID:</span>
                                        <span class="info-value" style="font-size: 12px;"><?= htmlspecialchars($payment_data['transaction_id']); ?></span>
                                    </div>
                                    <div class="info-line">
                                        <span class="info-label">Submitted:</span>
                                        <span class="info-value"><?= date('M d, Y H:i', strtotime($payment_data['created_at'])); ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Delivery Address -->
                            <div>
                                <div class="address-box">
                                    <h3>📍 Delivery Address</h3>
                                    <div class="address-text"><?= nl2br(htmlspecialchars($payment_data['delivery_address'])); ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Receipt Image -->
                        <?php if ($payment_data['receipt_image']): ?>
                            <div style="margin-top: 25px; border-top: 2px solid #f0f0f0; padding-top: 20px;">
                                <h3 style="color: #333; margin-bottom: 15px; font-size: 16px; font-weight: 700;">📄 Payment Receipt</h3>
                                <div class="receipt-preview">
                                    <?php if (strpos($payment_data['receipt_image'], '.pdf') !== false): ?>
                                        <div style="color: #666; margin-bottom: 10px;">📄 PDF Receipt</div>
                                        <a href="../<?= htmlspecialchars($payment_data['receipt_image']); ?>" target="_blank" class="receipt-link">📥 Download PDF</a>
                                    <?php else: ?>
                                        <img src="../<?= htmlspecialchars($payment_data['receipt_image']); ?>" alt="Receipt" class="receipt-image" onclick="window.open(this.src)">
                                        <a href="../<?= htmlspecialchars($payment_data['receipt_image']); ?>" target="_blank" class="receipt-link">🔍 View Full Size</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Action Buttons -->
                        <div class="action-buttons">
                            <button type="button" class="btn btn-approve" onclick="approvePayment(<?= $payment_data['id']; ?>)">
                                ✅ Approve Payment
                            </button>
                            <button type="button" class="btn btn-reject" onclick="openRejectModal(<?= $payment_data['id']; ?>)">
                                ❌ Reject Payment
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-payment-selected">
                        <div style="font-size: 60px; margin-bottom: 15px;">📋</div>
                        <p>Select a payment from the left to view details and verify receipt</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="modal">
    <div class="modal-content">
        <h3>❌ Reject Payment</h3>
        <p style="color: #666; margin-bottom: 15px; font-size: 14px;">Provide a reason for rejection. The user will be notified.</p>
        <form method="POST" id="rejectForm">
            <input type="hidden" name="payment_id" id="rejectPaymentId">
            <input type="hidden" name="action" value="reject">
            <textarea name="reason" placeholder="E.g., Receipt amount doesn't match, invalid receipt, duplicate payment..." required></textarea>
            <div class="modal-buttons">
                <button type="button" class="modal-cancel" onclick="closeRejectModal()">Cancel</button>
                <button type="submit" class="modal-confirm">Reject & Notify</button>
            </div>
        </form>
    </div>
</div>

<script>
    function approvePayment(paymentId) {
        if (confirm('Are you sure you want to approve this payment?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="payment_id" value="${paymentId}">
                <input type="hidden" name="action" value="approve">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    function openRejectModal(paymentId) {
        document.getElementById('rejectPaymentId').value = paymentId;
        document.getElementById('rejectModal').classList.add('active');
    }

    function closeRejectModal() {
        document.getElementById('rejectModal').classList.remove('active');
    }

    // Close modal when clicking outside
    document.getElementById('rejectModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeRejectModal();
        }
    });
</script>

</body>
</html>