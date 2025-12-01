<?php
session_start();

// Include database, layout, and security
require_once __DIR__ . '/../classes/db_connect.php';
require_once __DIR__ . '/../classes/Layout.php';
require_once __DIR__ . '/../config/security.php';

// Protect page: only admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bank_name = trim($_POST['bank_name']);
    $account_name = trim($_POST['account_name']);
    $account_number = trim($_POST['account_number']);
    $card_number = trim($_POST['card_number']);
    $card_name = trim($_POST['card_name']);
    $paypal_email = trim($_POST['paypal_email']);
    $payment_instructions = trim($_POST['payment_instructions']);
    
    // Encrypt sensitive data
    $encrypted_account = !empty($account_number) ? encryptData($account_number) : null;
    $encrypted_card = !empty($card_number) ? encryptData($card_number) : null;
    
    // Check if payment settings already exist
    $check = $conn->query("SELECT id FROM payment_settings LIMIT 1");
    
    if ($check && $check->num_rows > 0) {
        // Update existing
        $stmt = $conn->prepare("UPDATE payment_settings SET 
                                bank_name = ?, 
                                account_name = ?, 
                                account_number = ?,
                                card_number = ?,
                                card_name = ?,
                                paypal_email = ?,
                                payment_instructions = ?,
                                updated_at = NOW()");
        $stmt->bind_param("sssssss", $bank_name, $account_name, $encrypted_account, 
                         $encrypted_card, $card_name, $paypal_email, $payment_instructions);
    } else {
        // Insert new
        $stmt = $conn->prepare("INSERT INTO payment_settings 
                                (bank_name, account_name, account_number, card_number, card_name, paypal_email, payment_instructions) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $bank_name, $account_name, $encrypted_account, 
                         $encrypted_card, $card_name, $paypal_email, $payment_instructions);
    }
    
    if ($stmt->execute()) {
        $success_message = "Payment account details updated successfully!";
    } else {
        $error_message = "Failed to update payment details.";
    }
}

// Fetch existing payment settings
$result = $conn->query("SELECT * FROM payment_settings LIMIT 1");
$payment_settings = $result && $result->num_rows > 0 ? $result->fetch_assoc() : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Payment Accounts</title>
    <link rel="stylesheet" href="../navbar_sidebar.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #fff;
            margin: 0;
        }

        .main-content {
            padding: 30px;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
        }

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 20px rgba(102,126,234,0.3);
        }

        .page-header h2 {
            margin: 0;
            font-size: 32px;
            font-weight: 800;
        }

        .page-header p {
            margin: 10px 0 0;
            opacity: 0.9;
        }

        .settings-container {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-weight: 500;
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

        .security-notice {
            background: #fff3cd;
            border: 2px solid #ffc107;
            color: #856404;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .security-notice strong {
            display: block;
            margin-bottom: 5px;
        }

        .section-title {
            color: #333;
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #667eea;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 15px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
            box-sizing: border-box;
            font-family: inherit;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }

        .form-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
        }

        .save-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 10px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }

        .save-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(102,126,234,0.4);
        }

        .info-text {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
            font-style: italic;
        }

        .masked-value {
            display: inline-block;
            background: #e9ecef;
            padding: 8px 12px;
            border-radius: 5px;
            font-family: monospace;
            margin-top: 5px;
            color: #495057;
        }
    </style>
</head>
<body>

<?php Layout::navbar(); ?>
<?php Layout::sidebar(); ?>

<div class="main-content">
    <div class="page-header">
        <h2>🔒 Payment Account Settings</h2>
        <p>Securely manage your business payment account details</p>
    </div>

    <div class="settings-container">
        <div class="security-notice">
            <span style="font-size: 24px;">🔐</span>
            <div>
                <strong>Security Notice:</strong>
                All sensitive payment information is encrypted before storage. Customers will only see masked account numbers (e.g., ****1234).
            </div>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                ✓ <?= htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                ✗ <?= htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <!-- Bank Transfer Section -->
            <div class="form-section">
                <div class="section-title">🏦 Bank Transfer Details</div>
                
                <?php if ($payment_settings && !empty($payment_settings['account_number'])): ?>
                    <div style="margin-bottom: 15px;">
                        <strong>Current Account (Encrypted):</strong>
                        <div class="masked-value"><?= maskAccountNumber($payment_settings['account_number']); ?></div>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="bank_name">Bank Name *</label>
                    <input type="text" id="bank_name" name="bank_name" required
                           value="<?= $payment_settings ? htmlspecialchars($payment_settings['bank_name']) : ''; ?>"
                           placeholder="e.g., Chase Bank, Bank of America">
                    <p class="info-text">Enter the name of your bank</p>
                </div>

                <div class="form-group">
                    <label for="account_name">Account Holder Name *</label>
                    <input type="text" id="account_name" name="account_name" required
                           value="<?= $payment_settings ? htmlspecialchars($payment_settings['account_name']) : ''; ?>"
                           placeholder="e.g., John Doe">
                    <p class="info-text">Full name as it appears on the bank account</p>
                </div>

                <div class="form-group">
                    <label for="account_number">Account Number *</label>
                    <input type="text" id="account_number" name="account_number" required
                           placeholder="e.g., 1234567890">
                    <p class="info-text">🔒 This will be encrypted. Enter your bank account number</p>
                </div>
            </div>

            <!-- Card Payment Section -->
            <div class="form-section">
                <div class="section-title">💳 Card Payment Details</div>
                
                <?php if ($payment_settings && !empty($payment_settings['card_number'])): ?>
                    <div style="margin-bottom: 15px;">
                        <strong>Current Card (Encrypted):</strong>
                        <div class="masked-value"><?= maskCardNumber($payment_settings['card_number']); ?></div>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="card_number">Card Number</label>
                    <input type="text" id="card_number" name="card_number"
                           placeholder="e.g., 1234 5678 9012 3456" maxlength="19">
                    <p class="info-text">🔒 This will be encrypted. Card number for receiving payments (optional)</p>
                </div>

                <div class="form-group">
                    <label for="card_name">Cardholder Name</label>
                    <input type="text" id="card_name" name="card_name"
                           value="<?= $payment_settings ? htmlspecialchars($payment_settings['card_name']) : ''; ?>"
                           placeholder="e.g., JOHN DOE">
                    <p class="info-text">Name as it appears on the card</p>
                </div>
            </div>

            <!-- PayPal Section -->
            <div class="form-section">
                <div class="section-title">🅿️ PayPal Details</div>
                
                <div class="form-group">
                    <label for="paypal_email">PayPal Email</label>
                    <input type="email" id="paypal_email" name="paypal_email"
                           value="<?= $payment_settings ? htmlspecialchars($payment_settings['paypal_email'] ?? '') : ''; ?>"
                           placeholder="your-business@email.com">
                    <p class="info-text">Email address linked to your PayPal business account (optional)</p>
                </div>
            </div>

            <!-- Payment Instructions -->
            <div class="form-section">
                <div class="section-title">📝 Payment Instructions</div>
                
                <div class="form-group">
                    <label for="payment_instructions">Additional Instructions for Customers</label>
                    <textarea id="payment_instructions" name="payment_instructions" 
                              placeholder="e.g., Please include your order number in the payment reference..."><?= $payment_settings ? htmlspecialchars($payment_settings['payment_instructions'] ?? '') : ''; ?></textarea>
                    <p class="info-text">These instructions will be shown to customers during payment (optional)</p>
                </div>
            </div>

            <button type="submit" class="save-btn">
                🔒 Save Encrypted Payment Details
            </button>
        </form>
    </div>
</div>

<script>
// Format card number input
document.getElementById('card_number').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\s/g, '');
    let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
    e.target.value = formattedValue;
});
</script>

</body>
</html>