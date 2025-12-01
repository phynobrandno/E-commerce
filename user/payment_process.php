<?php
session_start();
require_once __DIR__ . '/../classes/db_connect.php';
require_once __DIR__ . '/../classes/UserLayout.php';

// Protect page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit();
}

// Check if order exists
if (!isset($_GET['order_id']) || !isset($_GET['method'])) {
    header("Location: cart.php");
    exit();
}

$order_id = intval($_GET['order_id']);
$payment_method = $_GET['method'];
$user_id = $_SESSION['user_id'];

// Fetch order details
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();

if (!$order) {
    header("Location: orders.php");
    exit();
}

// Fetch payment gateway settings from database
$gateway_settings = $conn->query("SELECT * FROM payment_gateways LIMIT 1")->fetch_assoc();

// PayPal Configuration
$paypal_client_id = $gateway_settings['paypal_client_id'] ?? '';
$paypal_secret = $gateway_settings['paypal_secret'] ?? '';
$paypal_mode = $gateway_settings['paypal_mode'] ?? 'sandbox'; // sandbox or live

// Stripe Configuration
$stripe_public_key = $gateway_settings['stripe_public_key'] ?? '';
$stripe_secret_key = $gateway_settings['stripe_secret_key'] ?? '';

// Set PayPal API URLs based on mode
if ($paypal_mode === 'live') {
    $paypal_api_url = 'https://api-m.paypal.com';
} else {
    $paypal_api_url = 'https://api-m.sandbox.paypal.com';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Process Payment - Order #<?= $order_id; ?></title>
    <link rel="stylesheet" href="../navbar_sidebar.css">
    
    <?php if ($payment_method === 'PayPal'): ?>
        <!-- PayPal SDK -->
        <script src="https://www.paypal.com/sdk/js?client-id=<?= htmlspecialchars($paypal_client_id); ?>&currency=USD"></script>
    <?php elseif ($payment_method === 'Stripe'): ?>
        <!-- Stripe SDK -->
        <script src="https://js.stripe.com/v3/"></script>
    <?php endif; ?>
    
    <style>
        body {
            background: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .payment-container {
            max-width: 600px;
            margin: 80px auto;
            padding: 20px;
        }
        
        .payment-card {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        .payment-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .payment-header h2 {
            color: #333;
            margin: 0 0 10px;
            font-size: 28px;
        }
        
        .payment-header p {
            color: #666;
            margin: 0;
        }
        
        .order-details {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .order-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .order-row:last-child {
            border-bottom: none;
            font-size: 20px;
            font-weight: bold;
            color: #28a745;
            padding-top: 15px;
        }
        
        .order-label {
            color: #666;
        }
        
        .order-value {
            color: #333;
            font-weight: 600;
        }
        
        /* PayPal Button Container */
        #paypal-button-container {
            margin: 20px 0;
        }
        
        /* Stripe Payment Form */
        #stripe-payment-form {
            margin: 20px 0;
        }
        
        .stripe-input {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        #card-element {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        #card-errors {
            color: #dc3545;
            font-size: 14px;
            margin-top: 10px;
        }
        
        .pay-button {
            width: 100%;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 10px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .pay-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(40,167,69,0.4);
        }
        
        .pay-button:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007bff;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
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
        
        .back-link {
            display: inline-block;
            color: #007bff;
            text-decoration: none;
            margin-bottom: 20px;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .security-badge {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            color: #666;
            font-size: 14px;
        }
        
        .security-badge svg {
            width: 16px;
            height: 16px;
            fill: #28a745;
            vertical-align: middle;
            margin-right: 5px;
        }
    </style>
</head>
<body>

<?php UserLayout::navbar(); ?>
<?php UserLayout::sidebar(); ?>

<div class="main-content">
    <div class="payment-container">
        <a href="orders.php" class="back-link">← Back to Orders</a>
        
        <div class="payment-card">
            <div class="payment-header">
                <h2>💳 Complete Payment</h2>
                <p>Order #<?= str_pad($order_id, 6, '0', STR_PAD_LEFT); ?></p>
            </div>
            
            <div class="order-details">
                <div class="order-row">
                    <span class="order-label">Order Total:</span>
                    <span class="order-value">$<?= number_format($order['total_amount'], 2); ?></span>
                </div>
                <div class="order-row">
                    <span class="order-label">Payment Method:</span>
                    <span class="order-value"><?= htmlspecialchars($payment_method); ?></span>
                </div>
                <div class="order-row">
                    <span class="order-label">Amount to Pay:</span>
                    <span class="order-value">$<?= number_format($order['total_amount'], 2); ?> USD</span>
                </div>
            </div>
            
            <?php if ($payment_method === 'PayPal'): ?>
                <!-- PayPal Payment -->
                <div id="paypal-button-container"></div>
                <div id="payment-status"></div>
                
            <?php elseif ($payment_method === 'Stripe'): ?>
                <!-- Stripe Payment -->
                <form id="stripe-payment-form">
                    <div id="card-element"></div>
                    <div id="card-errors" role="alert"></div>
                    <button type="submit" id="stripe-pay-button" class="pay-button">
                        Pay $<?= number_format($order['total_amount'], 2); ?>
                    </button>
                </form>
                <div id="payment-status"></div>
            <?php endif; ?>
            
            <div class="security-badge">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">
                    <path d="M5.338 1.59a61.44 61.44 0 0 0-2.837.856.481.481 0 0 0-.328.39c-.554 4.157.726 7.19 2.253 9.188a10.725 10.725 0 0 0 2.287 2.233c.346.244.652.42.893.533.12.057.218.095.293.118a.55.55 0 0 0 .101.025.615.615 0 0 0 .1-.025c.076-.023.174-.061.294-.118.24-.113.547-.29.893-.533a10.726 10.726 0 0 0 2.287-2.233c1.527-1.997 2.807-5.031 2.253-9.188a.48.48 0 0 0-.328-.39c-.651-.213-1.75-.56-2.837-.855C9.552 1.29 8.531 1.067 8 1.067c-.53 0-1.552.223-2.662.524zM5.072.56C6.157.265 7.31 0 8 0s1.843.265 2.928.56c1.11.3 2.229.655 2.887.87a1.54 1.54 0 0 1 1.044 1.262c.596 4.477-.787 7.795-2.465 9.99a11.775 11.775 0 0 1-2.517 2.453 7.159 7.159 0 0 1-1.048.625c-.28.132-.581.24-.829.24s-.548-.108-.829-.24a7.158 7.158 0 0 1-1.048-.625 11.777 11.777 0 0 1-2.517-2.453C1.928 10.487.545 7.169 1.141 2.692A1.54 1.54 0 0 1 2.185 1.43 62.456 62.456 0 0 1 5.072.56z"/>
                    <path d="M10.854 5.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 7.793l2.646-2.647a.5.5 0 0 1 .708 0z"/>
                </svg>
                Secure Payment Processing
            </div>
        </div>
    </div>
</div>

<?php if ($payment_method === 'PayPal'): ?>
<script>
    // PayPal Integration
    paypal.Buttons({
        createOrder: function(data, actions) {
            return actions.order.create({
                purchase_units: [{
                    amount: {
                        value: '<?= number_format($order['total_amount'], 2, '.', ''); ?>'
                    },
                    description: 'Order #<?= $order_id; ?>'
                }]
            });
        },
        onApprove: function(data, actions) {
            return actions.order.capture().then(function(details) {
                // Show loading
                document.getElementById('payment-status').innerHTML = `
                    <div class="loading">
                        <div class="spinner"></div>
                        <p>Processing payment...</p>
                    </div>
                `;
                
                // Send payment confirmation to server
                fetch('payment_confirm.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        order_id: <?= $order_id; ?>,
                        payment_method: 'PayPal',
                        transaction_id: details.id,
                        payer_email: details.payer.email_address,
                        payer_name: details.payer.name.given_name + ' ' + details.payer.name.surname,
                        amount: details.purchase_units[0].amount.value,
                        status: details.status
                    })
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        document.getElementById('payment-status').innerHTML = `
                            <div class="alert alert-success">
                                <strong>✓ Payment Successful!</strong><br>
                                Transaction ID: ${details.id}<br>
                                Redirecting to orders...
                            </div>
                        `;
                        setTimeout(() => {
                            window.location.href = 'orders.php?payment_success=1';
                        }, 2000);
                    } else {
                        document.getElementById('payment-status').innerHTML = `
                            <div class="alert alert-error">
                                <strong>✗ Error:</strong> ${result.message}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    document.getElementById('payment-status').innerHTML = `
                        <div class="alert alert-error">
                            <strong>✗ Error:</strong> Failed to process payment confirmation.
                        </div>
                    `;
                });
            });
        },
        onError: function(err) {
            document.getElementById('payment-status').innerHTML = `
                <div class="alert alert-error">
                    <strong>✗ Payment Failed</strong><br>
                    Please try again or contact support.
                </div>
            `;
        }
    }).render('#paypal-button-container');
</script>

<?php elseif ($payment_method === 'Stripe'): ?>
<script>
    // Stripe Integration
    const stripe = Stripe('<?= $stripe_public_key; ?>');
    const elements = stripe.elements();
    
    // Create card element
    const cardElement = elements.create('card', {
        style: {
            base: {
                fontSize: '16px',
                color: '#32325d',
                fontFamily: '"Segoe UI", Tahoma, Geneva, Verdana, sans-serif',
                '::placeholder': {
                    color: '#aab7c4'
                }
            },
            invalid: {
                color: '#fa755a',
                iconColor: '#fa755a'
            }
        }
    });
    
    cardElement.mount('#card-element');
    
    // Handle real-time validation errors
    cardElement.on('change', function(event) {
        const displayError = document.getElementById('card-errors');
        if (event.error) {
            displayError.textContent = event.error.message;
        } else {
            displayError.textContent = '';
        }
    });
    
    // Handle form submission
    const form = document.getElementById('stripe-payment-form');
    const submitButton = document.getElementById('stripe-pay-button');
    
    form.addEventListener('submit', async function(event) {
        event.preventDefault();
        
        submitButton.disabled = true;
        submitButton.textContent = 'Processing...';
        
        try {
            // Create payment intent on server
            const response = await fetch('create_payment_intent.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    order_id: <?= $order_id; ?>,
                    amount: <?= $order['total_amount'] * 100; ?> // Stripe uses cents
                })
            });
            
            const {clientSecret, error} = await response.json();
            
            if (error) {
                throw new Error(error);
            }
            
            // Confirm payment
            const {error: stripeError, paymentIntent} = await stripe.confirmCardPayment(clientSecret, {
                payment_method: {
                    card: cardElement
                }
            });
            
            if (stripeError) {
                document.getElementById('card-errors').textContent = stripeError.message;
                submitButton.disabled = false;
                submitButton.textContent = 'Pay $<?= number_format($order['total_amount'], 2); ?>';
            } else {
                // Payment successful
                document.getElementById('payment-status').innerHTML = `
                    <div class="alert alert-success">
                        <strong>✓ Payment Successful!</strong><br>
                        Transaction ID: ${paymentIntent.id}<br>
                        Redirecting to orders...
                    </div>
                `;
                
                // Confirm payment on server
                await fetch('payment_confirm.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        order_id: <?= $order_id; ?>,
                        payment_method: 'Stripe',
                        transaction_id: paymentIntent.id,
                        amount: <?= number_format($order['total_amount'], 2, '.', ''); ?>,
                        status: paymentIntent.status
                    })
                });
                
                setTimeout(() => {
                    window.location.href = 'orders.php?payment_success=1';
                }, 2000);
            }
        } catch (error) {
            document.getElementById('card-errors').textContent = error.message;
            submitButton.disabled = false;
            submitButton.textContent = 'Pay $<?= number_format($order['total_amount'], 2); ?>';
        }
    });
</script>
<?php endif; ?>

</body>
</html>