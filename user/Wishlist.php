<?php
session_start();

// Include database & layout
require_once __DIR__ . '/../classes/db_connect.php';
require_once __DIR__ . '/../classes/UserLayout.php';

// Protect user-only page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle remove from wishlist
if (isset($_GET['action']) && $_GET['action'] === 'remove' && isset($_GET['product_id'])) {
    $product_id = intval($_GET['product_id']);
    $conn->query("DELETE FROM wishlist WHERE user_id = $user_id AND product_id = $product_id");
    header("Location: wishlist.php");
    exit();
}

// Handle add to cart from wishlist
if (isset($_GET['action']) && $_GET['action'] === 'add_to_cart' && isset($_GET['product_id'])) {
    $product_id = intval($_GET['product_id']);

    // Check if already in cart
    $check_cart = $conn->query("SELECT id, quantity FROM cart WHERE user_id = $user_id AND product_id = $product_id");

    if ($check_cart->num_rows > 0) {
        // Update quantity
        $cart_item = $check_cart->fetch_assoc();
        $new_quantity = $cart_item['quantity'] + 1;
        $conn->query("UPDATE cart SET quantity = $new_quantity WHERE id = {$cart_item['id']}");
    } else {
        // Add to cart
        $conn->query("INSERT INTO cart (user_id, product_id, quantity) VALUES ($user_id, $product_id, 1)");
    }

    header("Location: wishlist.php?added=1");
    exit();
}

// Fetch wishlist items with product details
$query = "SELECT w.id AS wishlist_id, p.*, c.name AS category_name 
          FROM wishlist w 
          JOIN products p ON w.product_id = p.id 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE w.user_id = $user_id 
          ORDER BY w.created_at DESC";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>My Wishlist - User Dashboard</title>
    <link rel="stylesheet" href="../navbar_sidebar.css">
    <style>
        .main-content {
            margin-top: 70px;
            padding: 30px;
        }

        .page-header {
            background: linear-gradient(135deg, #fff 0%, #fff 100%);
            color: black;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 20px rgba(0, 123, 255, 0.3);
            text-align: center;
            font-family: 'Courier New', Courier, monospace;
            font-weight: bold;
        }

        .page-header h2 {
            margin: 0 0 10px 0;
            font-size: 32px;
        }

        .page-header p {
            margin: 0;
            opacity: 0.95;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .product-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            position: relative;
            height: 100%;
            cursor: pointer;
        }

        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .product-card-link {
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .product-card .image-container {
            position: relative;
            width: 100%;
            height: 240px;
            overflow: hidden;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .product-card:hover .image-container {
            background: #efefef;
        }

        .product-card img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            transition: transform 0.3s ease;
            padding: 8px;
            box-sizing: border-box;
        }

        .product-card:hover img {
            transform: scale(1.05);
        }

        .product-card .stock-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(255, 255, 255, 0.95);
            color: #333;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            z-index: 5;
        }

        .product-card .info {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .product-card h4 {
            margin: 0 0 10px 0;
            font-size: 18px;
            color: #333;
            line-height: 1.4;
            transition: color 0.3s ease;
        }

        .product-card:hover h4 {
            color: #667eea;
        }

        .product-card .category {
            display: inline-block;
            background: #e7f3ff;
            color: #1e88e5;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 10px;
            width: fit-content;
        }

        .product-card p {
            font-size: 14px;
            color: #666;
            margin-bottom: 12px;
            flex: 1;
            line-height: 1.6;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-card .price {
            font-weight: bold;
            color: #667eea;
            font-size: 24px;
            margin-bottom: 15px;
        }

        .product-actions {
            display: flex;
            gap: 10px;
            margin-top: auto;
        }

        .product-actions button,
        .product-actions a {
            flex: 1;
            text-align: center;
            padding: 12px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .add-to-cart-btn {
            background: #667eea;
            color: white;
        }

        .add-to-cart-btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }

        .remove-btn {
            background: #ff4757;
            color: white;
        }

        .remove-btn:hover {
            background: #ee5a6f;
            transform: translateY(-2px);
        }

        .empty-wishlist {
            text-align: center;
            padding: 80px 20px;
            color: #999;
        }

        .empty-wishlist i {
            font-size: 80px;
            margin-bottom: 20px;
            display: block;
            opacity: 0.3;
        }

        .empty-wishlist h3 {
            font-size: 24px;
            color: #666;
            margin-bottom: 10px;
        }

        .empty-wishlist p {
            font-size: 16px;
            margin-bottom: 30px;
        }

        .empty-wishlist a {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
        }

        .empty-wishlist a:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }

        @media (max-width: 1024px) {
            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            }

            .product-card .image-container {
                height: 200px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 20px;
            }

            .product-card .image-container {
                height: 180px;
            }

            .product-card h4 {
                font-size: 16px;
            }

            .product-card .price {
                font-size: 20px;
            }
        }

        @media (max-width: 600px) {
            .product-grid {
                grid-template-columns: 1fr;
            }

            .product-card .image-container {
                height: 220px;
            }
        }

        /* Fix sidebar layering */
        .sidebar {
            position: fixed !important;
            z-index: 1000 !important;
        }

        .navbar {
            position: fixed !important;
            z-index: 1100 !important;
        }

        .main-content {
            position: relative;
            z-index: 1 !important;
        }
    </style>
</head>

<body>

    <?php UserLayout::navbar(); ?>
    <?php UserLayout::sidebar(); ?>

    <div class="main-content">

        <!-- Page Header -->
        <div class="page-header">
            <p>❤️ Your favorite products saved for later</p>
        </div>

        <?php if (isset($_GET['added'])): ?>
            <div class="success-message">
                <span>✓</span>
                <span>Product added to cart successfully!</span>
            </div>
        <?php endif; ?>

        <!-- Products Grid -->
        <?php if ($result && $result->num_rows > 0): ?>
            <div class="product-grid">
                <?php while ($row = $result->fetch_assoc()):
                    // Fix image path
                    $image_path = !empty($row['image_url']) ? '/senior_try/' . $row['image_url'] : '/senior_try/uploads/default.jpg';
                ?>
                    <div class="product-card">
                        <!-- Entire product card is clickable and links to detail page -->
                        <a href="detail-page.php?id=<?= $row['id']; ?>" class="product-card-link">
                            <div class="image-container">
                                <img src="<?= htmlspecialchars($image_path); ?>"
                                    alt="<?= htmlspecialchars($row['name']); ?>"
                                    onerror="this.src='/senior_try/uploads/default.jpg'">
                                <span class="stock-badge"><?= $row['stock']; ?> in stock</span>
                            </div>
                            <div class="info">
                                <?php if (!empty($row['category_name'])): ?>
                                    <span class="category"><?= htmlspecialchars($row['category_name']); ?></span>
                                <?php endif; ?>

                                <h4><?= htmlspecialchars($row['name']); ?></h4>
                                <p><?= htmlspecialchars($row['description']); ?></p>
                                <div class="price">$<?= number_format($row['price'], 2); ?></div>
                            </div>
                        </a>

                        <!-- Action buttons outside the link to prevent navigation conflicts -->
                        <div class="product-actions">
                            <button class="add-to-cart-btn" onclick="window.location.href='wishlist.php?action=add_to_cart&product_id=<?= $row['id']; ?>'">
                                🛒 Add to Cart
                            </button>
                            <button class="remove-btn" onclick="if(confirm('Remove this item from your wishlist?')) { window.location.href='wishlist.php?action=remove&product_id=<?= $row['id']; ?>'; }">
                                ❌ Remove
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-wishlist">
                <i>💔</i>
                <h3>Your wishlist is empty</h3>
                <p>Start adding products you love to your wishlist!</p>
                <a href="dashboard.php">Browse Products</a>
            </div>
        <?php endif; ?>

    </div>

</body>

</html>