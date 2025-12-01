<?php
// confirm-payment.php - FIXED VERSION
require_once __DIR__ . '/../config/security.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../classes/db_connect.php';
require_once __DIR__ . '/../classes/UserLayout.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Protect page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error_message = null;

// Setup upload directory
$upload_dir = __DIR__ . '/../uploads/receipts/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}
chmod($upload_dir, 0777);

// Check checkout info
if (!isset($_SESSION['checkout_phone']) || !isset($_SESSION['checkout_address'])) {
    header("Location: checkout.php");
    exit();
}

// Fetch cart items
$stmt = $conn->prepare("SELECT c.id as cart_id, c.quantity, c.product_id, p.name, p.price, p.image_url, 
                       (c.quantity * p.price) as subtotal
                       FROM cart c
                       JOIN products p ON c.product_id = p.id
                       WHERE c.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_items = $stmt->get_result();

$total = 0;
$items = [];
while ($item = $cart_items->fetch_assoc()) {
    $items[] = $item;
    $total += $item['subtotal'];
}

if (empty($items)) {
    header("Location: cart.php");
    exit();
}

// Only process POST with file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment_with_receipt'])) {
    
    error_log("=== PAYMENT SUBMISSION START ===");
    error_log("POST data: " . print_r($_POST, true));
    error_log("FILES data: " . print_r($_FILES, true));
    
    // Get payment method and amount
    $payment_method = $_POST['payment_method'] ?? null;
    $amount = floatval($_POST['amount'] ?? 0);
    
    // Validate amount
    if (abs($amount - $total) > 0.01) {
        $error_message = "Invalid payment amount.";
        error_log("ERROR: Amount mismatch - Expected: $total, Got: $amount");
    }
    
    // CRITICAL: Check for file upload
    if (!isset($_FILES['payment_receipt'])) {
        $error_message = "No file input received. Form might not have enctype='multipart/form-data'";
        error_log("ERROR: No \$_FILES['payment_receipt']");
    } elseif ($_FILES['payment_receipt']['error'] === UPLOAD_ERR_NO_FILE) {
        $error_message = "Please upload your payment receipt/proof of payment.";
        error_log("ERROR: User did not select a file");
    } elseif ($_FILES['payment_receipt']['error'] !== UPLOAD_ERR_OK) {
        $error_message = "File upload error: " . $_FILES['payment_receipt']['error'];
        error_log("ERROR: Upload error code " . $_FILES['payment_receipt']['error']);
    } elseif (!isset($error_message)) {
        
        $receipt_file = $_FILES['payment_receipt'];
        error_log("File received: " . $receipt_file['name'] . " (" . $receipt_file['size'] . " bytes)");
        
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
        
        if (!in_array($receipt_file['type'], $allowed_types)) {
            $error_message = "Invalid file type: " . $receipt_file['type'] . ". Please upload an image (JPG, PNG, GIF) or PDF file.";
            error_log("ERROR: Invalid file type: " . $receipt_file['type']);
        } elseif ($receipt_file['size'] > 5 * 1024 * 1024) {
            $error_message = "File too large (" . number_format($receipt_file['size'] / 1024 / 1024, 2) . "MB). Max 5MB.";
            error_log("ERROR: File too large: " . $receipt_file['size']);
        } else {
            
            // Generate unique filename
            $file_extension = strtolower(pathinfo($receipt_file['name'], PATHINFO_EXTENSION));
            $receipt_filename = 'receipt_' . $user_id . '_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
            $receipt_path = $upload_dir . $receipt_filename;
            $receipt_db_path = 'uploads/receipts/' . $receipt_filename;
            
            error_log("Upload path: " . $receipt_path);
            error_log("DB path: " . $receipt_db_path);
            
            // Move uploaded file
            if (!move_uploaded_file($receipt_file['tmp_name'], $receipt_path)) {
                $error_message = "Failed to save receipt file. Check directory permissions.";
                error_log("ERROR: move_uploaded_file failed - tmp: " . $receipt_file['tmp_name'] . " to: " . $receipt_path);
            } elseif (!file_exists($receipt_path)) {
                $error_message = "Receipt file verification failed after upload.";
                error_log("ERROR: File not found after move");
            } else {
                
                error_log("SUCCESS: File moved to " . $receipt_path);
                
                try {
                    $conn->begin_transaction();
                    
                    $transaction_id = 'TXN' . time() . rand(1000, 9999);
                    $phone = $_SESSION['checkout_phone'];
                    $address = $_SESSION['checkout_address'];
                    
                    // Get user info
                    $user_stmt = $conn->prepare("SELECT email, username FROM users WHERE id = ?");
                    $user_stmt->bind_param("i", $user_id);
                    $user_stmt->execute();
                    $user_info = $user_stmt->get_result()->fetch_assoc();
                    $user_stmt->close();
                    
                    // Create order
                    $order_stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, status, payment_method, phone, delivery_address) 
                                                  VALUES (?, ?, 'pending', ?, ?, ?)");
                    $order_stmt->bind_param("idsss", $user_id, $amount, $payment_method, $phone, $address);
                    
                    if (!$order_stmt->execute()) {
                        throw new Exception("Order creation failed: " . $order_stmt->error);
                    }
                    
                    $order_id = $conn->insert_id;
                    $order_stmt->close();
                    error_log("Created order ID: $order_id");
                    
                    // Insert order items
                    foreach ($items as $item) {
                        $item_stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) 
                                                     VALUES (?, ?, ?, ?)");
                        $item_stmt->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
                        
                        if (!$item_stmt->execute()) {
                            throw new Exception("Order item insertion failed: " . $item_stmt->error);
                        }
                        $item_stmt->close();
                    }
                    error_log("Inserted order items");
                    
                    // Build payment insert based on method
                    $payment_stmt = null;
                    
                    if ($payment_method === 'card') {
                        $card_number = trim($_POST['card_number'] ?? '');
                        $card_name = trim($_POST['card_name'] ?? '');
                        $encrypted_card = encryptData($card_number);
                        
                        $payment_stmt = $conn->prepare(
                            "INSERT INTO payments (user_id, order_id, payment_method, amount, card_number, card_name, phone, delivery_address, receipt_image, transaction_id, status) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
                        );
                        $payment_stmt->bind_param("iisdssssss", $user_id, $order_id, $payment_method, $amount, $encrypted_card, $card_name, $phone, $address, $receipt_db_path, $transaction_id);
                        
                    } elseif ($payment_method === 'bank_transfer') {
                        $account_number = trim($_POST['account_number'] ?? '');
                        $account_name = trim($_POST['account_name'] ?? '');
                        $encrypted_account = encryptData($account_number);
                        
                        $payment_stmt = $conn->prepare(
                            "INSERT INTO payments (user_id, order_id, payment_method, amount, account_number, account_name, phone, delivery_address, receipt_image, transaction_id, status) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
                        );
                        $payment_stmt->bind_param("iisdssssss", $user_id, $order_id, $payment_method, $amount, $encrypted_account, $account_name, $phone, $address, $receipt_db_path, $transaction_id);
                        
                    } elseif ($payment_method === 'paypal') {
                        $email = trim($_POST['paypal_email'] ?? '');
                        
                        $payment_stmt = $conn->prepare(
                            "INSERT INTO payments (user_id, order_id, payment_method, amount, email, phone, delivery_address, receipt_image, transaction_id, status) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
                        );
                        $payment_stmt->bind_param("iisdsssss", $user_id, $order_id, $payment_method, $amount, $email, $phone, $address, $receipt_db_path, $transaction_id);
                    }
                    
                    if (!$payment_stmt || !$payment_stmt->execute()) {
                        throw new Exception("Payment insertion failed: " . ($payment_stmt ? $payment_stmt->error : 'No statement'));
                    }
                    
                    $payment_id = $conn->insert_id;
                    $payment_stmt->close();
                    error_log("Created payment ID: $payment_id with receipt: $receipt_db_path");
                    
                    // Delete cart
                    $cart_stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
                    $cart_stmt->bind_param("i", $user_id);
                    $cart_stmt->execute();
                    $cart_stmt->close();
                    
                    // Create admin notification
                    $admin_query = $conn->query("SELECT id FROM users WHERE role = 'admin'");
                    while ($admin = $admin_query->fetch_assoc()) {
                        $notif_stmt = $conn->prepare("INSERT INTO user_notifications (user_id, title, message, type, is_read) 
                                                      VALUES (?, ?, ?, 'warning', 0)");
                        $notif_title = "New Payment Receipt - Order #" . $order_id;
                        $notif_msg = "User " . $user_info['username'] . " submitted " . ucfirst(str_replace('_', ' ', $payment_method)) . " payment of $" . number_format($amount, 2) . ". Transaction: " . $transaction_id;
                        $notif_stmt->bind_param("iss", $admin['id'], $notif_title, $notif_msg);
                        $notif_stmt->execute();
                        $notif_stmt->close();
                    }
                    
                    // Clean session
                    unset($_SESSION['checkout_phone']);
                    unset($_SESSION['checkout_address']);
                    
                    $conn->commit();
                    error_log("=== PAYMENT SUBMISSION SUCCESS ===");
                    
                    $_SESSION['payment_success'] = "Payment receipt uploaded successfully! Your order is pending admin verification.";
                    header("Location: wait-receipt-verification.php?order_id=" . $order_id . "&transaction_id=" . $transaction_id);
                    exit();
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    error_log("ERROR: " . $e->getMessage());
                    
                    if (file_exists($receipt_path)) {
                        unlink($receipt_path);
                    }
                    
                    $error_message = $e->getMessage();
                }
            }
        }
    }
}

// GET request - show form
$payment_method = $_POST['payment_method'] ?? $_SESSION['payment_method'] ?? 'card';
$amount = floatval($_POST['amount'] ?? $total);

$stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

$payment_query = $conn->query("SELECT * FROM payment_settings LIMIT 1");
$payment_settings = $payment_query && $payment_query->num_rows > 0 ? $payment_query->fetch_assoc() : null;

// Decrypt account number if it exists
if ($payment_settings && !empty($payment_settings['account_number'])) {
    $payment_settings['account_number'] = decryptData($payment_settings['account_number']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Payment</title>
    <link rel="stylesheet" href="../navbar_sidebar.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; }
        .main-content { padding: 20px; min-height: 100vh; }
        .confirm-container { max-width: 800px; margin: 40px auto; background: white; border-radius: 20px; padding: 40px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); }
        .confirm-header { text-align: center; margin-bottom: 40px; }
        .confirm-header h2 { font-size: 32px; font-weight: 800; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin-bottom: 10px; }
        .confirm-header p { color: #666; font-size: 16px; }
        .alert { padding: 15px 20px; border-radius: 12px; margin-bottom: 25px; font-weight: 500; }
        .alert-error { background: #fff5f5; color: #c53030; border-left: 4px solid #f56565; }
        .alert-success { background: #f0fdf4; color: #166534; border-left: 4px solid #22c55e; }
        .payment-info-box { background: linear-gradient(135deg, #f8f9ff 0%, #f0f3ff 100%); border: 2px solid #e0e7ff; border-radius: 15px; padding: 25px; margin-bottom: 30px; }
        .info-row { display: flex; gap: 12px; font-size: 16px; padding: 12px; background: white; border-radius: 8px; margin-bottom: 10px; }
        .info-label { font-weight: 600; color: #333; min-width: 150px; }
        .info-value { color: #666; flex: 1; }
        .upload-section { background: #f0fdf4; border: 2px solid #86efac; border-radius: 15px; padding: 25px; margin-bottom: 30px; }
        .upload-section h3 { color: #166534; margin-bottom: 20px; }
        .upload-container { background: white; border: 3px dashed #86efac; border-radius: 12px; padding: 30px; text-align: center; cursor: pointer; transition: all 0.3s; }
        .upload-container:hover { border-color: #22c55e; background: #f9fafb; }
        .upload-container.dragover { border-color: #22c55e; background: #f0fdf4; }
        #payment_receipt { display: none; }
        .file-preview { margin-top: 20px; display: none; }
        .preview-item { background: white; padding: 15px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; }
        .btn-group { display: flex; gap: 15px; margin-top: 25px; }
        .btn { flex: 1; padding: 15px; border: none; border-radius: 10px; font-size: 16px; font-weight: bold; cursor: pointer; transition: all 0.3s; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .btn-primary:hover:not(:disabled) { transform: translateY(-3px); }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        .checkbox-container { display: flex; gap: 15px; margin-bottom: 20px; padding: 15px; background: white; border-radius: 8px; border: 2px solid #e0e0e0; }
        .checkbox-container input { accent-color: #667eea; }
    </style>
</head>
<body>

<?php UserLayout::navbar(); ?>
<?php UserLayout::sidebar(); ?>

<div class="main-content">
    <div class="confirm-container">
        <div class="confirm-header">
            <h2>📋 Confirm Payment</h2>
            <p>Upload your payment receipt</p>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-error">✗ <?= htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="paymentForm">
            <div class="payment-info-box">
                <h3>💳 Payment Details</h3>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?= htmlspecialchars($user_info['email']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Amount:</span>
                    <span class="info-value" style="color: #667eea; font-weight: 700; font-size: 18px;">$<?= number_format($amount, 2); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone:</span>
                    <span class="info-value"><?= htmlspecialchars($_SESSION['checkout_phone']); ?></span>
                </div>
                <div style="background: white; padding: 15px; border-radius: 8px; margin-bottom: 10px; border-left: 4px solid #667eea;">
                    <div style="display: flex; justify-content: space-between; align-items: start; gap: 10px;">
                        <div style="flex: 1;">
                            <span style="font-weight: 600; color: #333; display: block; margin-bottom: 5px;">📍 Delivery Address:</span>
                            <span class="info-value" id="displayAddress"><?= nl2br(htmlspecialchars($_SESSION['checkout_address'])); ?></span>
                        </div>
                        <button type="button" class="btn-edit" id="editAddressBtn" style="background: #667eea; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; white-space: nowrap;">✏️ Edit</button>
                    </div>
                </div>

                <!-- Address Edit Modal -->
                <div id="addressModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; padding: 20px; overflow-y: auto;">
                    <div style="background: white; border-radius: 15px; padding: 30px; max-width: 500px; margin: 50px auto; box-shadow: 0 10px 40px rgba(0,0,0,0.2);">
                        <h3 style="color: #333; margin-bottom: 20px; font-size: 20px; font-weight: 700;">✏️ Edit Delivery Address</h3>
                        <textarea id="editAddressInput" style="width: 100%; padding: 12px; border: 2px solid #e0e7ff; border-radius: 8px; font-family: Arial; font-size: 14px; min-height: 120px; resize: vertical; box-sizing: border-box;"><?= htmlspecialchars($_SESSION['checkout_address']); ?></textarea>
                        <div style="display: flex; gap: 10px; margin-top: 20px;">
                            <button type="button" id="cancelAddressBtn" style="flex: 1; padding: 12px; background: #6c757d; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">Cancel</button>
                            <button type="button" id="saveAddressBtn" style="flex: 1; padding: 12px; background: #667eea; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">Save Address</button>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($payment_method === 'bank_transfer'): ?>
            <div style="background: linear-gradient(135deg, #fff9e6 0%, #fff3cd 100%); border: 2px solid #ffc107; border-radius: 15px; padding: 25px; margin-bottom: 30px;">
                <h3 style="color: #856404; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">🏦 Bank Transfer Instructions</h3>
                
                <div style="background: white; padding: 20px; border-radius: 10px; margin-bottom: 15px; border-left: 4px solid #667eea;">
                    <strong style="display: block; color: #333; margin-bottom: 12px; font-size: 16px;">📋 Transfer Details:</strong>
                    <div style="color: #555; line-height: 2;">
                        <div><strong>🏦 Bank Name:</strong> <?= htmlspecialchars($payment_settings['bank_name'] ?? 'N/A'); ?></div>
                        <div><strong>👤 Account Name:</strong> <?= htmlspecialchars($payment_settings['account_name'] ?? 'N/A'); ?></div>
                        <div><strong>🔢 Account Number:</strong> <code style="background: #f0f0f0; padding: 5px 10px; border-radius: 5px; font-weight: bold;"><?= htmlspecialchars($payment_settings['account_number'] ?? 'N/A'); ?></code></div>
                        <div><strong>💰 Amount to Send:</strong> <span style="color: #667eea; font-weight: bold; font-size: 18px;">$<?= number_format($amount, 2); ?></span></div>
                        <?php if (!empty($payment_settings['payment_instructions'])): ?>
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e0e0e0;"><strong>📝 Additional Instructions:</strong><br><?= nl2br(htmlspecialchars($payment_settings['payment_instructions'])); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <ol style="color: #856404; margin-left: 20px; line-height: 2;">
                    <li><strong>Send exactly $<?= number_format($amount, 2); ?></strong> to the account above</li>
                    <li><strong>Use your name</strong> as the transfer reference</li>
                    <li><strong>Keep your transfer receipt</strong> - you'll need it for verification</li>
                    <li><strong>Upload your receipt</strong> in the section below</li>
                    <li><strong>We'll verify and confirm</strong> your payment within 24 hours</li>
                </ol>
            </div>
            <?php endif; ?>

            <div class="upload-section">
                <h3>📸 Upload Receipt</h3>
                <div class="upload-container" id="uploadContainer">
                    <div style="font-size: 40px; margin-bottom: 10px;">📤</div>
                    <div style="color: #166534; font-weight: 600; margin-bottom: 5px;">Click to upload or drag and drop</div>
                    <div style="color: #666; font-size: 14px;">PNG, JPG, GIF or PDF (Max 5MB)</div>
                    <input type="file" name="payment_receipt" id="payment_receipt" accept="image/*,.pdf" required>
                </div>
                <div class="file-preview" id="filePreview">
                    <div class="preview-item">
                        <span id="fileName"></span>
                        <button type="button" onclick="removeFile()" class="btn btn-secondary" style="flex: 0; padding: 8px 15px;">Remove</button>
                    </div>
                </div>
            </div>

            <div class="checkbox-container">
                <input type="checkbox" id="confirm_check" required>
                <label for="confirm_check">I confirm I have uploaded a valid payment receipt</label>
            </div>

            <input type="hidden" name="payment_method" value="<?= htmlspecialchars($payment_method); ?>">
            <input type="hidden" name="amount" value="<?= htmlspecialchars($amount); ?>">
            <?php if ($payment_method === 'card'): ?>
                <input type="hidden" name="card_number" value="<?= htmlspecialchars($_POST['card_number'] ?? ''); ?>">
                <input type="hidden" name="card_name" value="<?= htmlspecialchars($_POST['card_name'] ?? ''); ?>">
            <?php elseif ($payment_method === 'bank_transfer'): ?>
                <input type="hidden" name="account_number" value="<?= htmlspecialchars($_POST['account_number'] ?? ''); ?>">
                <input type="hidden" name="account_name" value="<?= htmlspecialchars($_POST['account_name'] ?? ''); ?>">
            <?php elseif ($payment_method === 'paypal'): ?>
                <input type="hidden" name="paypal_email" value="<?= htmlspecialchars($_POST['paypal_email'] ?? ''); ?>">
            <?php endif; ?>

            <div class="btn-group">
                <button type="button" class="btn btn-secondary" onclick="window.history.back()">← Go Back</button>
                <button type="submit" name="confirm_payment_with_receipt" class="btn btn-primary" id="submitBtn" disabled>📤 Submit Receipt</button>
            </div>
        </form>
    </div>
</div>

<script>
    const uploadContainer = document.getElementById('uploadContainer');
    const fileInput = document.getElementById('payment_receipt');
    const filePreview = document.getElementById('filePreview');
    const fileName = document.getElementById('fileName');
    const confirmCheck = document.getElementById('confirm_check');
    const submitBtn = document.getElementById('submitBtn');

    uploadContainer.addEventListener('click', () => fileInput.click());

    uploadContainer.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadContainer.classList.add('dragover');
    });

    uploadContainer.addEventListener('dragleave', () => {
        uploadContainer.classList.remove('dragover');
    });

    uploadContainer.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadContainer.classList.remove('dragover');
        fileInput.files = e.dataTransfer.files;
        handleFileSelect();
    });

    fileInput.addEventListener('change', handleFileSelect);

    function handleFileSelect() {
        const file = fileInput.files[0];
        if (!file) return;

        const allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
        if (!allowed.includes(file.type)) {
            alert('Invalid file type. Use JPG, PNG, GIF, or PDF');
            fileInput.value = '';
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            alert('File too large. Max 5MB');
            fileInput.value = '';
            return;
        }

        fileName.textContent = file.name;
        uploadContainer.style.display = 'none';
        filePreview.style.display = 'block';
        updateButton();
    }

    function removeFile() {
        fileInput.value = '';
        uploadContainer.style.display = 'block';
        filePreview.style.display = 'none';
        updateButton();
    }

    function updateButton() {
        submitBtn.disabled = !confirmCheck.checked || !fileInput.files.length;
    }

    confirmCheck.addEventListener('change', updateButton);

    document.getElementById('paymentForm').addEventListener('submit', function(e) {
        if (!fileInput.files.length) {
            e.preventDefault();
            alert('Please select a file to upload');
        }
    });

    // Address edit functionality
    const editAddressBtn = document.getElementById('editAddressBtn');
    const addressModal = document.getElementById('addressModal');
    const editAddressInput = document.getElementById('editAddressInput');
    const saveAddressBtn = document.getElementById('saveAddressBtn');
    const cancelAddressBtn = document.getElementById('cancelAddressBtn');
    const displayAddress = document.getElementById('displayAddress');
    let addressHiddenInput = document.querySelector('input[name="delivery_address"]') || 
                             (() => {
                                 const input = document.createElement('input');
                                 input.type = 'hidden';
                                 input.name = 'delivery_address';
                                 input.value = '<?= htmlspecialchars($_SESSION['checkout_address']); ?>';
                                 document.getElementById('paymentForm').appendChild(input);
                                 return input;
                             })();

    editAddressBtn.addEventListener('click', () => {
        addressModal.style.display = 'block';
    });

    cancelAddressBtn.addEventListener('click', () => {
        addressModal.style.display = 'none';
    });

    saveAddressBtn.addEventListener('click', () => {
        const newAddress = editAddressInput.value.trim();
        if (!newAddress) {
            alert('Please enter a valid address');
            return;
        }
        
        displayAddress.innerHTML = newAddress.split('\n').join('<br>');
        addressHiddenInput.value = newAddress;
        addressModal.style.display = 'none';
    });

    // Close modal when clicking outside
    addressModal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
</script>

</body>
</html>