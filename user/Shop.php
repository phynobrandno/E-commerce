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

// Handle wishlist toggle (add/remove)
if (isset($_GET['wishlist_action']) && isset($_GET['product_id'])) {
    $product_id = intval($_GET['product_id']);
    $action = $_GET['wishlist_action'];

    if ($action === 'add') {
        // Check if already in wishlist
        $check = $conn->query("SELECT id FROM wishlist WHERE user_id = $user_id AND product_id = $product_id");
        if ($check->num_rows === 0) {
            $conn->query("INSERT INTO wishlist (user_id, product_id) VALUES ($user_id, $product_id)");
        }
    } elseif ($action === 'remove') {
        $conn->query("DELETE FROM wishlist WHERE user_id = $user_id AND product_id = $product_id");
    }

    // Redirect back to shop to prevent form resubmission
    header("Location: shop.php" . ($_SERVER['QUERY_STRING'] ? '?' . http_build_query(array_filter($_GET, function ($key) {
        return !in_array($key, ['wishlist_action', 'product_id']);
    }, ARRAY_FILTER_USE_KEY)) : ''));
    exit();
}

// Get filter/search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;

// Build query
$query = "SELECT p.*, c.name AS category_name FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE p.stock > 0";

if ($search !== '') {
    $search_term = $conn->real_escape_string($search);
    $query .= " AND (p.name LIKE '%$search_term%' OR p.description LIKE '%$search_term%')";
}

if ($category_filter > 0) {
    $query .= " AND p.category_id = $category_filter";
}

$query .= " ORDER BY p.created_at DESC";

// Fetch products
$result = $conn->query($query);

// Fetch categories for filter
$categories = $conn->query("SELECT * FROM categories ORDER BY name ASC");

// Check if product is in cart
$cart_items = [];
$cart_query = $conn->query("SELECT product_id FROM cart WHERE user_id = $user_id");
if ($cart_query) {
    while ($row = $cart_query->fetch_assoc()) {
        $cart_items[] = $row['product_id'];
    }
}

// Check if product is in wishlist
$wishlist_items = [];
$wishlist_query = $conn->query("SELECT product_id FROM wishlist WHERE user_id = $user_id");
if ($wishlist_query) {
    while ($row = $wishlist_query->fetch_assoc()) {
        $wishlist_items[] = $row['product_id'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Shop - User Dashboard</title>
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
            flex-grow: initial;
            font-weight: 8000;
        }

        .page-header h2 {
            margin: 0 0 10px 0;
            font-size: 32px;
        }

        .page-header p {
            margin: 0;
            opacity: 0.95;
        }

        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-section input[type="text"],
        .filter-section select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            flex: 1;
            min-width: 200px;
        }

        .filter-section button {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: 0.3s;
        }

        .filter-section button:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }

        .filter-section .clear-btn {
            background: #6c757d;
        }

        .filter-section .clear-btn:hover {
            background: #5a6268;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 25px;
            margin-bottom: 30px;
        }

        @media (max-width: 1600px) {
            .product-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (max-width: 1200px) {
            .product-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 900px) {
            .product-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            .product-grid {
                grid-template-columns: 1fr;
            }
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
        }

        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .product-card .image-container {
            position: relative;
            width: 100%;
            height: 200px;
            overflow: hidden;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
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
            z-index: 9;
        }

        /* Wishlist Heart Button */
        .wishlist-btn {
            position: absolute;
            top: 12px;
            left: 12px;
            background: rgba(255, 255, 255, 0.95);
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            z-index: 10;
        }

        .wishlist-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        }

        .wishlist-btn.in-wishlist {
            background: #ff4757;
        }

        .wishlist-btn .heart-icon {
            font-size: 18px;
            transition: all 0.3s ease;
        }

        .wishlist-btn:not(.in-wishlist) .heart-icon {
            color: #ff4757;
        }

        .wishlist-btn.in-wishlist .heart-icon {
            color: white;
        }

        .product-card .info {
            padding: 15px;
            flex: 1;
            display: flex;
            flex-direction: column;
            cursor: pointer;
        }

        .product-card .info:hover {
            background: #f8f9fa;
        }

        .product-card h4 {
            margin: 0 0 8px 0;
            font-size: 16px;
            color: #333;
            line-height: 1.4;
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
            font-size: 13px;
            color: #666;
            margin-bottom: 10px;
            flex: 1;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-card .price {
            font-weight: bold;
            color: #667eea;
            font-size: 20px;
            margin-bottom: 10px;
        }

        .cart-controls {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: auto;
        }

        .cart-controls a {
            flex: 1;
            text-align: center;
            font-size: 28px;
            font-weight: bold;
            text-decoration: none;
            padding: 10px;
            border-radius: 8px;
            transition: 0.3s;
            background: transparent;
        }

        .cart-controls .minus-btn {
            color: #dc3545;
        }

        .cart-controls .minus-btn:hover {
            transform: scale(1.05);
        }

        .cart-controls .plus-btn {
            color: #667eea;
        }

        .cart-controls .plus-btn:hover {
            transform: scale(1.05);
        }

        .no-products {
            text-align: center;
            padding: 60px 20px;
            color: #999;
            font-size: 18px;
            grid-column: 1 / -1;
        }

        .no-products i {
            font-size: 64px;
            margin-bottom: 20px;
            display: block;
            opacity: 0.3;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .filter-section {
                flex-direction: column;
            }

            .filter-section input,
            .filter-section select,
            .filter-section button {
                width: 100%;
            }

            .product-card .image-container {
                height: 180px;
            }
        }

        /* Fix sidebar layering so products go behind */
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
            <p>Discover amazing products at great prices</p>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; width: 100%;">
                <input type="text" name="search" placeholder="🔍 Search products..." value="<?= htmlspecialchars($search); ?>">

                <select name="category">
                    <option value="0">All Categories</option>
                    <?php
                    if ($categories && $categories->num_rows > 0) {
                        while ($cat = $categories->fetch_assoc()):
                    ?>
                            <option value="<?= $cat['id']; ?>" <?= $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($cat['name']); ?>
                            </option>
                    <?php
                        endwhile;
                    }
                    ?>
                </select>

                <button type="submit">Filter</button>
                <a href="shop.php" class="clear-btn" style="text-decoration: none; display: inline-block; background: #6c757d; color: white; padding: 10px 25px; border-radius: 8px; font-weight: 600;">Clear</a>
            </form>
        </div>

        <!-- Products Grid -->
        <div class="product-grid">
            <?php
            if ($result && $result->num_rows > 0):
                while ($row = $result->fetch_assoc()):
                    // Fix image path
                    $image_path = !empty($row['image_url']) ? '/senior_try/' . $row['image_url'] : '/senior_try/uploads/default.jpg';
                    $in_cart = in_array($row['id'], $cart_items);
                    $in_wishlist = in_array($row['id'], $wishlist_items);

                    // Build query string for wishlist toggle
                    $current_params = $_GET;
                    $current_params['product_id'] = $row['id'];
                    $current_params['wishlist_action'] = $in_wishlist ? 'remove' : 'add';
                    $wishlist_url = 'shop.php?' . http_build_query($current_params);
            ?>
                    <div class="product-card">
                        <div class="image-container">
                            <img src="<?= htmlspecialchars($image_path); ?>"
                                alt="<?= htmlspecialchars($row['name']); ?>"
                                onerror="this.src='/senior_try/uploads/default.jpg'">

                            <!-- Wishlist Heart Button -->
                            <a href="<?= htmlspecialchars($wishlist_url); ?>"
                                class="wishlist-btn <?= $in_wishlist ? 'in-wishlist' : ''; ?>"
                                title="<?= $in_wishlist ? 'Remove from wishlist' : 'Add to wishlist'; ?>">
                                <span class="heart-icon"><?= $in_wishlist ? '❤️' : '🤍'; ?></span>
                            </a>

                            <span class="stock-badge"><?= $row['stock']; ?> in stock</span>
                        </div>
                        <div class="info" onclick="window.location.href='detail-page.php?id=<?= $row['id']; ?>';">
                            <?php if (!empty($row['category_name'])): ?>
                                <span class="category"><?= htmlspecialchars($row['category_name']); ?></span>
                            <?php endif; ?>

                            <h4><?= htmlspecialchars($row['name']); ?></h4>
                            <p><?= htmlspecialchars($row['description']); ?></p>
                            <div class="price">$<?= number_format($row['price'], 2); ?></div>

                            <div class="cart-controls">
                                <a href="cart.php" class="minus-btn" onclick="event.stopPropagation();">-</a>
                                <a href="cart.php?product_id=<?= $row['id']; ?>&action=add" class="plus-btn" onclick="event.stopPropagation();">+</a>
                            </div>
                        </div>
                    </div>
                <?php
                endwhile;
            else:
                ?>
                <div class="no-products">
                    <i>📦</i>
                    <p>No products found. Try adjusting your filters.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>

</body>

</html>