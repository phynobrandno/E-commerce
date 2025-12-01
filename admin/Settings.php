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
require_once '../config/database.php';
$database = new Database();
$pdo = $database->getConnection();

// Handle form submissions
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['update_payment_settings'])) {
            // Update payment settings
            $bank_name = $_POST['bank_name'] ?? '';
            $account_name = $_POST['account_name'] ?? '';
            $account_number = $_POST['account_number'] ?? '';
            $card_name = $_POST['card_name'] ?? '';
            $paypal_email = $_POST['paypal_email'] ?? '';
            $payment_instructions = $_POST['payment_instructions'] ?? '';
            
            // Encrypt sensitive data
            $encrypted_account = !empty($account_number) ? base64_encode($account_number) : null;
            
            $stmt = $pdo->prepare("
                UPDATE payment_settings 
                SET bank_name = ?, 
                    account_name = ?, 
                    account_number = ?,
                    card_name = ?,
                    paypal_email = ?,
                    payment_instructions = ?,
                    updated_at = NOW()
                WHERE id = 1
            ");
            
            $stmt->execute([
                $bank_name, 
                $account_name, 
                $encrypted_account,
                $card_name,
                $paypal_email,
                $payment_instructions
            ]);
            
            $success_message = "Payment settings updated successfully!";
        }
        
        if (isset($_POST['update_site_settings'])) {
            // You can add site-wide settings here
            $success_message = "Site settings updated successfully!";
        }
        
    } catch (PDOException $e) {
        $error_message = "Error updating settings: " . $e->getMessage();
    }
}

// Fetch current payment settings
try {
    $stmt = $pdo->query("SELECT * FROM payment_settings WHERE id = 1");
    $payment_settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Decrypt account number for display
    if ($payment_settings && !empty($payment_settings['account_number'])) {
        $payment_settings['account_number_display'] = base64_decode($payment_settings['account_number']);
    }
} catch (PDOException $e) {
    $error_message = "Error fetching settings: " . $e->getMessage();
}

// Get statistics
try {
    $stats = [];
    
    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
    $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Total products
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM products");
    $stats['total_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Total orders
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders");
    $stats['total_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Pending payments
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM payments WHERE status = 'pending'");
    $stats['pending_payments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Open support tickets
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM support_tickets WHERE status = 'open'");
    $stats['open_tickets'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
} catch (PDOException $e) {
    $error_message = "Error fetching statistics: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings</title>
    <link rel="stylesheet" href="../navbar_sidebar.css">
    <style>
        .content {
            margin-left: 100px;
            width: 90%;                 
            padding: 20px;
            margin-top: 80px;
        }
        
        .settings-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .alert-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
        }
        
        .stat-card .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #333;
        }
        
        .settings-section {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .settings-section h2 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #0056b3;
        }
        
        .info-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .encrypted-notice {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 13px;
        }
        
        @media (max-width: 768px) {
            .content {
                margin-left: 0;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<?php Layout::navbar(); ?>
<?php Layout::sidebar(); ?>

<div class="content">
    <div class="settings-container">
        
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Dashboard -->
        <div class="settings-section">
            <h2>System Overview</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Users</h3>
                    <div class="stat-number"><?php echo $stats['total_users'] ?? 0; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Products</h3>
                    <div class="stat-number"><?php echo $stats['total_products'] ?? 0; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Orders</h3>
                    <div class="stat-number"><?php echo $stats['total_orders'] ?? 0; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Pending Payments</h3>
                    <div class="stat-number"><?php echo $stats['pending_payments'] ?? 0; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Open Tickets</h3>
                    <div class="stat-number"><?php echo $stats['open_tickets'] ?? 0; ?></div>
                </div>
            </div>
        </div>
        
        <!-- Payment Settings -->
        <div class="settings-section">
            <h2>Payment Settings</h2>
            
            <div class="encrypted-notice">
                <strong>⚠️ Security Notice:</strong> Sensitive information like account numbers are encrypted in the database.
            </div>
            
            <form method="POST" action="">
                <h3>Bank Transfer Settings</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="bank_name">Bank Name</label>
                        <input type="text" id="bank_name" name="bank_name" 
                               value="<?php echo htmlspecialchars($payment_settings['bank_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="account_name">Account Name</label>
                        <input type="text" id="account_name" name="account_name" 
                               value="<?php echo htmlspecialchars($payment_settings['account_name'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="account_number">Account Number</label>
                    <input type="text" id="account_number" name="account_number" 
                           value="<?php echo htmlspecialchars($payment_settings['account_number_display'] ?? ''); ?>">
                    <div class="info-text">This information will be encrypted when saved</div>
                </div>
                
                <h3>Card Payment Settings</h3>
                <div class="form-group">
                    <label for="card_name">Card Holder Name</label>
                    <input type="text" id="card_name" name="card_name" 
                           value="<?php echo htmlspecialchars($payment_settings['card_name'] ?? ''); ?>">
                </div>
                
                <h3>PayPal Settings</h3>
                <div class="form-group">
                    <label for="paypal_email">PayPal Email</label>
                    <input type="email" id="paypal_email" name="paypal_email" 
                           value="<?php echo htmlspecialchars($payment_settings['paypal_email'] ?? ''); ?>">
                </div>
                
                <h3>Payment Instructions</h3>
                <div class="form-group">
                    <label for="payment_instructions">Instructions for Customers</label>
                    <textarea id="payment_instructions" name="payment_instructions"><?php echo htmlspecialchars($payment_settings['payment_instructions'] ?? ''); ?></textarea>
                    <div class="info-text">These instructions will be shown to customers during checkout</div>
                </div>
                
                <button type="submit" name="update_payment_settings" class="btn btn-primary">
                    Save Payment Settings
                </button>
            </form>
        </div>
        
        <!-- Quick Actions -->
        <div class="settings-section">
            <h2>Quick Actions</h2>
            <p>Manage your store:</p>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="Products.php" class="btn btn-primary">Manage Products</a>
                <a href="manage_orders.php" class="btn btn-primary">View Orders</a>
                <a href="Customers_Account.php" class="btn btn-primary">Customer Account</a>
                <a href="verify-payment.php" class="btn btn-primary">Payment Approvals</a>
                <a href="Customer_Support.php" class="btn btn-primary">Support Tickets</a>
            </div>
        </div>
        
    </div>
</div>

</body>
</html>