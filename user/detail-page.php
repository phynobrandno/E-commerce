<?php
session_start();
require_once __DIR__ . '/../classes/UserLayout.php';
require_once __DIR__ . '/../classes/db_connect.php';

// Get product ID from URL
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($product_id <= 0) {
    header("Location: dashboard.php");
    exit();
}

// Fetch product details
$stmt = $conn->prepare("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

// If product not found, redirect
if (!$product) {
    header("Location: dashboard.php");
    exit();
}

// Decode colors from JSON - properly handle all edge cases
$colors = [];
if (!empty($product['colors'])) {
    // Don't try to decode if it's just '0' or invalid
    if ($product['colors'] !== '0' && $product['colors'] !== 'NULL') {
        $decoded = json_decode($product['colors'], true);
        // Only use decoded value if it's a valid array with items
        if (is_array($decoded) && count($decoded) > 0) {
            $colors = $decoded;
        }
    }
}

// Handle add to cart
$cart_message = '';
$message_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    // Check if user is logged in
    if (!isset($_SESSION['id']) && !isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit();
    }

    // Get user ID from session
    $user_id = $_SESSION['id'] ?? $_SESSION['user_id'];
    $quantity = intval($_POST['quantity'] ?? 1);

    if ($quantity > 0 && $quantity <= $product['stock']) {
        // Get selected colors from POST
        $selected_colors = isset($_POST['item_colors']) ? $_POST['item_colors'] : array();
        
        // Validate: if product has colors, must select one color per quantity
        if (!empty($colors)) {
            if (empty($selected_colors) || count($selected_colors) !== $quantity) {
                $cart_message = "❌ Please select exactly " . $quantity . " color(s) - one for each item!";
                $message_type = "error";
            } else {
                // Validate all colors are valid
                $valid_colors = true;
                foreach ($selected_colors as $color) {
                    if (!in_array($color, $colors)) {
                        $valid_colors = false;
                        break;
                    }
                }

                if (!$valid_colors) {
                    $cart_message = "❌ Invalid color selection!";
                    $message_type = "error";
                } else {
                    // Store colors as JSON string
                    $colors_json = json_encode($selected_colors);
                    
                    $check_stmt = $conn->prepare("SELECT id, quantity, color_data FROM cart WHERE user_id = ? AND product_id = ?");
                    $check_stmt->bind_param("ii", $user_id, $product_id);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();

                    if ($check_result->num_rows > 0) {
                        $cart_item = $check_result->fetch_assoc();
                        $new_quantity = $cart_item['quantity'] + $quantity;
                        
                        // Merge colors
                        $existing_colors = json_decode($cart_item['color_data'], true) ?: array();
                        $merged_colors = array_merge($existing_colors, $selected_colors);
                        $merged_colors_json = json_encode($merged_colors);
                        
                        $update_stmt = $conn->prepare("UPDATE cart SET quantity = ?, color_data = ? WHERE user_id = ? AND product_id = ?");
                        $update_stmt->bind_param("isii", $new_quantity, $merged_colors_json, $user_id, $product_id);
                        $update_stmt->execute();
                        $update_stmt->close();
                        $cart_message = "✅ Product quantity updated in cart!";
                        $message_type = "success";
                    } else {
                        $insert_stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity, color_data) VALUES (?, ?, ?, ?)");
                        $insert_stmt->bind_param("iiis", $user_id, $product_id, $quantity, $colors_json);
                        $insert_stmt->execute();
                        $insert_stmt->close();
                        $cart_message = "✅ Product added to cart successfully!";
                        $message_type = "success";
                    }
                    $check_stmt->close();
                    
                    // Redirect to cart.php after successful add
                    header("Location: cart.php");
                    exit();
                }
            }
        } else {
            // No colors - add normally
            $check_stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
            $check_stmt->bind_param("ii", $user_id, $product_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $cart_item = $check_result->fetch_assoc();
                $new_quantity = $cart_item['quantity'] + $quantity;
                $update_stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
                $update_stmt->bind_param("iii", $new_quantity, $user_id, $product_id);
                $update_stmt->execute();
                $update_stmt->close();
                $cart_message = "✅ Product quantity updated in cart!";
                $message_type = "success";
            } else {
                $insert_stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                $insert_stmt->bind_param("iii", $user_id, $product_id, $quantity);
                $insert_stmt->execute();
                $insert_stmt->close();
                $cart_message = "✅ Product added to cart successfully!";
                $message_type = "success";
            }
            $check_stmt->close();
            
            header("Location: cart.php");
            exit();
        }
    } else {
        $cart_message = "❌ Invalid quantity or exceeds stock!";
        $message_type = "error";
    }
}

// Handle add to wishlist
$wishlist_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_wishlist'])) {
    // Check if user is logged in
    if (!isset($_SESSION['id']) && !isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit();
    }

    // Get user ID from session (works with both $_SESSION['id'] and $_SESSION['user_id'])
    $user_id = $_SESSION['id'] ?? $_SESSION['user_id'];
    
    $check_stmt = $conn->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
    $check_stmt->bind_param("ii", $user_id, $product_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $wishlist_message = "⚠️ This product is already in your wishlist!";
    } else {
        $insert_stmt = $conn->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
        $insert_stmt->bind_param("ii", $user_id, $product_id);
        $insert_stmt->execute();
        $insert_stmt->close();
        $wishlist_message = "❤️ Added to wishlist!";
    }
    $check_stmt->close();
    
    // Redirect to wishlist page after successful add
    header("Location: wishlist.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']); ?> - Product Details</title>
    <link rel="stylesheet" href="../navbar_sidebar.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
        }

        .page-container {
            margin-left: 100px;
            padding: 30px;
            width: calc(100% - 100px);
            margin-top: 70px;
            transition: all 0.35s ease-in-out;
        }

        .page-container.sidebar-expanded {
            margin-left: 230px;
            width: calc(100% - 230px);
        }

        .breadcrumb {
            margin-bottom: 25px;
            font-size: 14px;
        }

        .breadcrumb a {
            color: #007bff;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .product-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            padding: 40px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            align-items: start;
        }

        .product-image {
            position: relative;
            width: 100%;
            height: 500px;
            background: #f8f9fa;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .product-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            padding: 20px;
        }

        .product-info h1 {
            font-size: 32px;
            color: #333;
            margin-bottom: 10px;
            line-height: 1.3;
        }

        .product-category {
            color: #999;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .product-price {
            font-size: 36px;
            color: #007bff;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .product-stock {
            font-size: 16px;
            margin-bottom: 20px;
            padding: 10px 15px;
            background: #e8f5e9;
            border-left: 4px solid #28a745;
            border-radius: 4px;
            color: #2e7d32;
        }

        .product-stock.low {
            background: #fff3e0;
            border-left-color: #f57c00;
            color: #e65100;
        }

        .product-stock.out {
            background: #ffebee;
            border-left-color: #c62828;
            color: #b71c1c;
        }

        .divider {
            height: 1px;
            background: #eee;
            margin: 20px 0;
        }

        .product-description {
            color: #555;
            line-height: 1.6;
            font-size: 16px;
            margin-bottom: 25px;
        }

        .product-attributes {
            margin-bottom: 25px;
        }

        .attribute {
            margin-bottom: 15px;
            background: #f8f9fa;
            padding: 12px 15px;
            border-radius: 6px;
            border-left: 4px solid #007bff;
        }

        .attribute-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            display: block;
            font-size: 14px;
        }

        .attribute-value {
            color: #666;
            font-size: 15px;
            word-break: break-word;
        }

        .quantity-section {
            margin-bottom: 25px;
        }

        .quantity-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
            display: block;
        }

        .quantity-input {
            display: flex;
            align-items: center;
            gap: 10px;
            width: fit-content;
        }

        .quantity-input input {
            width: 80px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            text-align: center;
        }

        .quantity-input button {
            width: 40px;
            height: 40px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 18px;
            transition: all 0.3s ease;
        }

        .quantity-input button:hover {
            background: #f0f0f0;
        }

        .colors-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            border-left: 4px solid #28a745;
            margin-bottom: 25px;
        }

        .colors-section-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            display: block;
            font-size: 14px;
        }

        .color-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .color-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 3px solid #ddd;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            position: relative;
        }

        .color-circle:hover {
            transform: scale(1.15);
            border-color: #333;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }

        .color-circle.white {
            border-color: #ccc;
        }

        .color-circle input[type="checkbox"] {
            display: none;
        }

        .selected-colors-display {
            background: white;
            padding: 15px;
            border-radius: 6px;
            border: 2px dashed #007bff;
            min-height: 80px;
        }

        .selected-colors-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 12px;
            font-size: 13px;
            display: block;
        }

        .selected-items {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .selected-item {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #e8f4f8;
            padding: 8px 12px;
            border-radius: 20px;
            border-left: 3px solid #007bff;
            font-size: 13px;
            color: #333;
        }

        .selected-item.incomplete {
            background: #fff5f5;
            border-left-color: #dc3545;
            color: #dc3545;
        }

        .item-number {
            font-weight: 600;
            min-width: 35px;
        }

        .color-swatch {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 1px solid #999;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            flex: 1;
            padding: 15px 25px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #333;
            border: 2px solid #ddd;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
            transform: translateY(-2px);
        }

        .btn-back {
            display: inline-block;
            margin-bottom: 20px;
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            transition: 0.3s;
        }

        .btn-back:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        @media (max-width: 1024px) {
            .product-container {
                grid-template-columns: 1fr;
                gap: 30px;
                padding: 30px;
            }

            .product-image {
                height: 400px;
            }

            .product-info h1 {
                font-size: 26px;
            }

            .product-price {
                font-size: 28px;
            }
        }

        @media (max-width: 768px) {
            .page-container {
                margin-left: 0;
                width: 100%;
                padding: 20px;
            }

            .product-container {
                padding: 20px;
                gap: 20px;
            }

            .product-image {
                height: 300px;
            }

            .product-info h1 {
                font-size: 22px;
            }

            .product-price {
                font-size: 24px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .color-buttons {
                gap: 8px;
            }

            .color-circle {
                width: 40px;
                height: 40px;
            }
        }
    </style>
</head>
<body>

<?php UserLayout::navbar(); ?>
<?php UserLayout::sidebar(); ?>

<div class="page-container">

    <!-- Back Button -->
    <a href="dashboard.php" class="btn-back">← Back to Dashboard</a>

    <!-- Messages -->
    <?php if (!empty($cart_message)) { ?>
        <div class="message <?= $message_type; ?>">
            <?= $cart_message; ?>
        </div>
    <?php } ?>
    <?php if (!empty($wishlist_message)) { ?>
        <div class="message <?= strpos($wishlist_message, 'already') !== false ? 'error' : 'success'; ?>">
            <?= $wishlist_message; ?>
        </div>
    <?php } ?>

    <!-- Product Container -->
    <div class="product-container">

        <!-- Product Image -->
        <div class="product-image">
            <img src="<?= htmlspecialchars('../' . $product['image_url']); ?>" 
                 alt="<?= htmlspecialchars($product['name']); ?>"
                 onerror="this.src='../images/placeholder.png'">
        </div>

        <!-- Product Info -->
        <div class="product-info">
            <h1><?= htmlspecialchars($product['name']); ?></h1>
            <div class="product-category">
                📁 <?= htmlspecialchars($product['category_name'] ?: 'Uncategorized'); ?>
            </div>

            <div class="product-price">
                $<?= number_format($product['price'], 2); ?>
            </div>

            <?php if ($product['stock'] > 10) { ?>
                <div class="product-stock">
                    ✅ In Stock (<?= $product['stock']; ?> available)
                </div>
            <?php } elseif ($product['stock'] > 0) { ?>
                <div class="product-stock low">
                    ⚠️ Low Stock (<?= $product['stock']; ?> available)
                </div>
            <?php } else { ?>
                <div class="product-stock out">
                    ❌ Out of Stock
                </div>
            <?php } ?>

            <div class="divider"></div>

            <!-- Description -->
            <div class="product-description">
                <?= nl2br(htmlspecialchars($product['description'])); ?>
            </div>

            <!-- Attributes -->
            <div class="product-attributes">
                <?php if (!empty($product['sizes'])) { ?>
                    <div class="attribute">
                        <span class="attribute-label">📏 Available Sizes:</span>
                        <span class="attribute-value"><?= htmlspecialchars($product['sizes']); ?></span>
                    </div>
                <?php } ?>

                <?php if (!empty($product['materials'])) { ?>
                    <div class="attribute">
                        <span class="attribute-label">🧵 Materials:</span>
                        <span class="attribute-value"><?= htmlspecialchars($product['materials']); ?></span>
                    </div>
                <?php } ?>

                <?php if (!empty($product['brief_details'])) { ?>
                    <div class="attribute">
                        <span class="attribute-label">✨ Details:</span>
                        <span class="attribute-value"><?= htmlspecialchars($product['brief_details']); ?></span>
                    </div>
                <?php } ?>
            </div>

            <div class="divider"></div>

            <!-- Quantity & Actions -->
            <?php if ($product['stock'] > 0) { ?>
                <form method="POST">
                    <input type="hidden" name="product_id" value="<?= $product_id; ?>">
                    
                    <div class="quantity-section">
                        <label class="quantity-label">Quantity:</label>
                        <div class="quantity-input">
                            <button type="button" onclick="decreaseQty()">−</button>
                            <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?= $product['stock']; ?>" readonly onchange="updateColorDisplay()">
                            <button type="button" onclick="increaseQty(<?= $product['stock']; ?>)">+</button>
                        </div>
                    </div>

                    <!-- Color Selection (only if colors exist) -->
                    <?php if (!empty($colors) && count($colors) > 0) { ?>
                        <div class="colors-section">
                            <span class="colors-section-title">🎨 Choose Color(s):</span>
                            <div class="color-buttons">
                                <?php 
                                foreach ($colors as $color) { 
                                    $color = htmlspecialchars($color);
                                    $isWhite = strtolower($color) === '#ffffff' || strtolower($color) === '#fff';
                                ?>
                                    <label class="color-circle <?php echo $isWhite ? 'white' : ''; ?>" 
                                           style="background-color: <?= $color; ?>;"
                                           title="<?= $color; ?>"
                                           onclick="addColor('<?= $color; ?>')">
                                        <input type="checkbox" value="<?= $color; ?>" />
                                    </label>
                                <?php } ?>
                            </div>

                            <!-- Selected Colors Display -->
                            <div class="selected-colors-display">
                                <span class="selected-colors-title">Selected Colors:</span>
                                <div class="selected-items" id="selectedItemsList">
                                    <div class="selected-item incomplete">
                                        <span class="item-number">Item 1:</span>
                                        <span>Select a color</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>

                    <div class="action-buttons">
                        <button type="submit" name="add_to_cart" class="btn btn-primary" onclick="return validateAndSubmit()">
                            🛒 Add to Cart
                        </button>
                        <button type="submit" name="add_to_wishlist" class="btn btn-secondary">
                            ❤️ Add to Wishlist
                        </button>
                    </div>
                </form>
            <?php } else { ?>
                <div class="action-buttons">
                    <button class="btn btn-primary" disabled>
                        Out of Stock
                    </button>
                </div>
            <?php } ?>

        </div>

    </div>

</div>

<script>
    const availableColors = <?= json_encode($colors); ?>;
    let selectedColors = [];

    function decreaseQty() {
        const input = document.getElementById('quantity');
        if (parseInt(input.value) > 1) {
            input.value = parseInt(input.value) - 1;
            updateColorDisplay();
        }
    }

    function increaseQty(max) {
        const input = document.getElementById('quantity');
        if (parseInt(input.value) < max) {
            input.value = parseInt(input.value) + 1;
            updateColorDisplay();
        }
    }

    function addColor(color) {
        const quantity = parseInt(document.getElementById('quantity').value);
        
        if (selectedColors.length < quantity) {
            selectedColors.push(color);
        } else {
            alert(`You can only select ${quantity} color(s) for ${quantity} item(s)`);
        }
        
        updateColorDisplay();
    }

    function removeColor(index) {
        selectedColors.splice(index, 1);
        updateColorDisplay();
    }

    function updateColorDisplay() {
        const quantity = parseInt(document.getElementById('quantity').value);
        const container = document.getElementById('selectedItemsList');
        container.innerHTML = '';

        // If no colors available, skip display
        if (availableColors.length === 0) return;

        for (let i = 0; i < quantity; i++) {
            const itemDiv = document.createElement('div');
            itemDiv.className = 'selected-item';
            
            if (i < selectedColors.length) {
                itemDiv.classList.remove('incomplete');
                itemDiv.innerHTML = `
                    <span class="item-number">Item ${i + 1}:</span>
                    <div class="color-swatch" style="background-color: ${selectedColors[i]};"></div>
                    <span>${selectedColors[i]}</span>
                    <button type="button" onclick="removeColor(${i})" style="background: none; border: none; cursor: pointer; color: inherit; font-size: 16px;">✕</button>
                `;
            } else {
                itemDiv.classList.add('incomplete');
                itemDiv.innerHTML = `
                    <span class="item-number">Item ${i + 1}:</span>
                    <span>Select a color</span>
                `;
            }
            
            container.appendChild(itemDiv);
        }

        // If quantity changed and we have more colors than needed, trim
        if (selectedColors.length > quantity) {
            selectedColors = selectedColors.slice(0, quantity);
        }
    }

    // Sidebar hover
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.querySelector('.sidebar');
        const pageContainer = document.querySelector('.page-container');

        if (sidebar && pageContainer) {
            sidebar.addEventListener('mouseenter', function() {
                pageContainer.classList.add('sidebar-expanded');
            });
            
            sidebar.addEventListener('mouseleave', function() {
                pageContainer.classList.remove('sidebar-expanded');
            });
        }
    });

    function validateAndSubmit() {
        const quantity = parseInt(document.getElementById('quantity').value);
        
        // If no colors available, allow submission
        if (availableColors.length === 0) {
            return true;
        }

        // If colors exist, must select correct number of colors
        if (selectedColors.length !== quantity) {
            alert(`Please select exactly ${quantity} color(s) - one for each item!`);
            return false;
        }

        // Add hidden inputs for each selected color to the form
        const form = document.querySelector('form');
        
        // Remove any existing color inputs
        const existingInputs = form.querySelectorAll('input[name="item_colors[]"]');
        existingInputs.forEach(input => input.remove());

        // Add new color inputs
        selectedColors.forEach((color) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'item_colors[]';
            input.value = color;
            form.appendChild(input);
        });

        return true;
    }
</script>

</body>
</html>