<?php
session_start();
require_once __DIR__ . '/../classes/UserLayout.php';
require_once __DIR__ . '/../classes/db_connect.php';

// Fetch categories
$categories = $conn->query("SELECT * FROM categories ORDER BY name ASC");

// Preserve search/filter values
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '0';

// Query for FEATURED products (created within last 7 days)
$featured_products_query = "SELECT * FROM products 
                           WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                           ORDER BY created_at DESC";
$featured_products = $conn->query($featured_products_query);

// Query for ALL products
$all_products_query = "SELECT * FROM products WHERE 1=1";
if (!empty($search)) {
    $search_escaped = $conn->real_escape_string($search);
    $all_products_query .= " AND name LIKE '%$search_escaped%'";
}
if (!empty($category_filter) && $category_filter != '0') {
    $category_escaped = $conn->real_escape_string($category_filter);
    $all_products_query .= " AND category_id = '$category_escaped'";
}
$all_products_query .= " ORDER BY created_at DESC";
$all_products = $conn->query($all_products_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
    <link rel="stylesheet" href="../navbar_sidebar.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: white;
            color: #222;
            margin: 0;
            padding: 0;
        }

        /*************** PAGE CONTAINER ***************/
        .page-container {
            margin-left: 100px;
            padding: 30px;
            max-width: 100%;
            width: calc(100% - 100px);
            box-sizing: border-box;
            transition: margin-left 0.35s ease-in-out, width 0.35s ease-in-out;
        }

        .page-container.sidebar-expanded {
            margin-left: 230px;
            width: calc(100% - 230px);
        }

        /*************** CATEGORY CONTAINER ***************/
        .category-wrapper {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
            width: 100%;
            margin-top: 70px;
        }

        .category-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 12px;
            color: #333;
        }

        .category-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            width: 100%;
        }

        .category-btn {
            padding: 8px 14px;
            background: white;
            border: 1px solid #ccc;
            color: #333;
            border-radius: 8px;
            font-size: 15px;
            text-decoration: none;
            transition: all 0.25s ease;
        }

        .category-btn:hover {
            background: #f2f2f2;
            transform: translateY(-2px);
        }

        /*************** FILTER SECTION ***************/
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            width: 100%;
        }

        .filter-section form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            width: 100%;
            align-items: center;
            justify-content: center;
        }

        .filter-section input[type="text"],
        .filter-section select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            min-width: 200px;
            flex: 1;
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

        /*************** 3D CAROUSEL SECTION ***************/
       /*************** 3D CAROUSEL SECTION ***************/
.featured-section {
    background: white;
    padding: 50px 30px;
    border-radius: 12px;
    margin-bottom: 40px;
    width: 100%;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
    opacity: 0.99;
}

.carousel-item.prev {
    transform: translateX(-280px) translateZ(0) rotateY(45deg) scale(0.8);
    opacity: 0.99;
}

.carousel-item.next {
    transform: translateX(280px) translateZ(0) rotateY(-40deg) scale(0.8);
    opacity:1;
}
        .featured-title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 40px;
            color: #333;
            text-align: center;
        }

        .carousel-wrapper {
            position: relative;
            width: 100%;
            height: 320px;
            display: flex;
            justify-content: center;
            align-items: center;
            perspective: 1200px;
            margin: 0 auto;
            overflow: visible;
            background: linear-gradient(135deg, #fff 0%, #fff 100%);
            border-radius: 12px;
            padding: 20px 0;
            box-sizing: border-box;
        }

        .carousel {
            position: relative;
            width: 100%;
            height: 100%;
            transform-style: preserve-3d;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .carousel-item {
            position: absolute;
            width: 200px;
            height: 280px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            cursor: pointer;
            transition: all 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            transform-style: preserve-3d;
            display: flex;
            flex-direction: column;
            border: 1px solid #e0e0e0;
        }

        .carousel-item img {
            width: 100%;
            height: 160px;
            object-fit: contain;
            background: #ffffff;
            padding: 10px;
            box-sizing: border-box;
        }

        .carousel-item-content {
            padding: 15px;
            text-align: center;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .carousel-item h4 {
            font-size: 14px;
            color: #333;
            font-weight: 600;
            line-height: 1.3;
            margin: 0 0 5px 0;
        }

        .carousel-item p {
            font-size: 15px;
            font-weight: 700;
            color: #667eea;
            margin: 5px 0;
        }

        .carousel-item a {
            display: inline-block;
            padding: 8px 14px;
            background: #333;
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
            transition: 0.25s;
            margin-top: auto;
        }

        .carousel-item a:hover {
            background: #555;
        }

        .carousel-item.active {
            z-index: 10;
            transform: translateZ(80px) scale(1.08);
            filter: drop-shadow(0 25px 50px rgba(0, 0, 0, 0.4));
            background: white;
        }

        .carousel-item.prev {
            transform: translateX(-280px) translateZ(0) rotateY(45deg) scale(0.8);
            opacity: 0.7;
        }

        .carousel-item.next {
            transform: translateX(280px) translateZ(0) rotateY(-45deg) scale(0.8);
            opacity: 0.7;
        }

        .carousel-item.prev-far {
            transform: translateX(-600px) translateZ(-100px) rotateY(60deg) scale(0);
            opacity: 0;
            visibility: hidden;
        }

        .carousel-item.next-far {
            transform: translateX(600px) translateZ(-100px) rotateY(-60deg) scale(0);
            opacity: 0;
            visibility: hidden;
        }

        .carousel-controls {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 30px;
        }

        .carousel-btn {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: 2px solid #667eea;
            background: white;
            color: #667eea;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .carousel-btn:hover {
            background: #667eea;
            color: white;
            transform: scale(1.1);
        }

        .carousel-dots {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 25px;
        }

        .carousel-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #ddd;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .carousel-dot.active {
            background: #667eea;
            width: 30px;
            border-radius: 5px;
        }

        /*************** PRODUCTS GRID ***************/
        h3 {
            font-size: 22px;
            margin: 30px 0 15px;
            font-weight: bold;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
            width: 100%;
            min-height: 400px;
        }

        .product-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            text-align: center;
            padding-bottom: 15px;
            height: 100%;
            position: relative;
        }

        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .product-card img {
            width: 100%;
            height: 250px;
            object-fit: contain;
            background: #f8f8f8;
            padding: 10px;
            box-sizing: border-box;
        }

        .product-card h4 {
            font-size: 16px;
            color: #333;
            margin: 12px 8px 5px;
            line-height: 1.3;
            min-height: 2.6em;
        }

        .product-card p {
            color: #666;
            font-size: 14px;
            margin: 8px 0;
            font-weight: 600;
        }

        .product-card a {
            display: inline-block;
            margin-top: auto;
            padding: 10px 20px;
            background: #333;
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            transition: 0.25s;
            margin-bottom: 10px;
        }

        .product-card a:hover {
            background: #555;
            transform: translateY(-2px);
        }

        .no-products {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 16px;
            grid-column: 1 / -1;
        }

        /*************** RESPONSIVE ***************/
        @media (max-width: 1024px) {
            .carousel-wrapper {
                height: 280px;
            }

            .carousel-item {
                width: 170px;
                height: 250px;
            }

            .carousel-item img {
                height: 140px;
            }

            .carousel-item.prev {
                transform: translateX(-250px) translateZ(0) rotateY(40deg) scale(0.7);
            }

            .carousel-item.next {
                transform: translateX(250px) translateZ(0) rotateY(-40deg) scale(0.7);
            }

            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
                gap: 20px;
            }
            
            .product-card img {
                height: 200px;
            }
        }

        @media (max-width: 768px) {
            .page-container {
                margin-left: 0;
                padding: 20px;
            }

            .filter-section form {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-section input,
            .filter-section select,
            .filter-section button,
            .filter-section .clear-btn {
                width: 100%;
            }

            .featured-section {
                padding: 30px 20px;
            }

            .featured-title {
                font-size: 22px;
                margin-bottom: 25px;
            }

            .carousel-wrapper {
                height: 260px;
            }

            .carousel-item {
                width: 150px;
                height: 230px;
            }

            .carousel-item img {
                height: 120px;
            }

            .carousel-item.prev {
                transform: translateX(-200px) translateZ(0) rotateY(40deg) scale(0.65);
            }

            .carousel-item.next {
                transform: translateX(200px) translateZ(0) rotateY(-40deg) scale(0.65);
            }

            .carousel-item h4 {
                font-size: 12px;
            }

            .carousel-item p {
                font-size: 12px;
            }

            .carousel-btn {
                width: 40px;
                height: 40px;
                font-size: 18px;
            }

            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
                gap: 15px;
            }
            
            .product-card img {
                height: 160px;
            }
            
            .product-card h4 {
                font-size: 14px;
                margin: 8px 6px 3px;
            }
            
            .product-card p {
                font-size: 12px;
            }

            h3 {
                font-size: 18px;
            }
        }
    </style>
</head>
<body>

<?php UserLayout::navbar(); ?>
<?php UserLayout::sidebar(); ?>

<div class="page-container">

    <!-- CATEGORY SECTION -->
    <div class="category-wrapper">
        <div class="category-title">Categories</div>
        <div class="category-container">
            <a href="shop.php" class="category-btn">All</a>
            <?php
            $categories->data_seek(0);
            while ($cat = $categories->fetch_assoc()) { ?>
                <a href="shop.php?category=<?= $cat['id']; ?>" class="category-btn">
                    <?= htmlspecialchars($cat['name']); ?>
                </a>
            <?php } ?>
        </div>
    </div>

    <!-- FILTER SECTION -->
    <div class="filter-section">
        <form method="GET">
            <input type="text" name="search" placeholder="🔍 Search products..." value="<?= htmlspecialchars($search); ?>">
            <select name="category">
                <option value="0">All Categories</option>
                <?php
                $categories->data_seek(0);
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
            <a href="shop.php" class="clear-btn" style="text-decoration: none; display: inline-block; color: white; padding: 10px 25px; border-radius: 8px; font-weight: 600;">Clear</a>
        </form>
    </div>

    <!-- FEATURED PRODUCTS 3D CAROUSEL -->
    <?php if ($featured_products && $featured_products->num_rows > 0) { ?>
        <div class="featured-section">
            <div class="featured-title">🌟 New & Featured Products (Last 7 Days)</div>
            
            <div class="carousel-wrapper">
                <div class="carousel" id="carousel">
                    <?php 
                    $featured_products->data_seek(0);
                    while ($prod = $featured_products->fetch_assoc()) { 
                    ?>
                        <div class="carousel-item">
                            <img src="../<?= htmlspecialchars($prod['image_url']); ?>" alt="<?= htmlspecialchars($prod['name']); ?>" onerror="this.src='../images/placeholder.png'">
                            <div class="carousel-item-content">
                                <h4><?= htmlspecialchars($prod['name']); ?></h4>
                                <p>$<?= number_format($prod['price'], 2); ?></p>
                                <a href="shop.php?product=<?= $prod['id']; ?>">View Details</a>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <div class="carousel-controls">
                <button class="carousel-btn" onclick="prevSlide()">❮</button>
                <button class="carousel-btn" onclick="nextSlide()">❯</button>
            </div>

            <div class="carousel-dots" id="dotsContainer">
                <!-- Dots created by JavaScript -->
            </div>
        </div>

        <script>
            let currentIndex = 0;
            const carouselItems = document.querySelectorAll('.carousel-item');
            const totalItems = carouselItems.length;
            const dotsContainer = document.getElementById('dotsContainer');

            // Create dots
            carouselItems.forEach((_, index) => {
                const dot = document.createElement('div');
                dot.className = 'carousel-dot';
                if (index === 0) dot.classList.add('active');
                dot.addEventListener('click', () => {
                    currentIndex = index;
                    updateCarousel();
                });
                dotsContainer.appendChild(dot);
            });

            function updateCarousel() {
                carouselItems.forEach((item, index) => {
                    item.classList.remove('active', 'prev', 'next', 'prev-far', 'next-far');

                    const diff = (index - currentIndex + totalItems) % totalItems;

                    if (diff === 0) {
                        item.classList.add('active');
                    } else if (diff === 1) {
                        item.classList.add('next');
                    } else if (diff === totalItems - 1) {
                        item.classList.add('prev');
                    } else if (diff < totalItems / 2) {
                        item.classList.add('next-far');
                    } else {
                        item.classList.add('prev-far');
                    }
                });

                // Update dots
                document.querySelectorAll('.carousel-dot').forEach((dot, index) => {
                    dot.classList.toggle('active', index === currentIndex);
                });
            }

            function nextSlide() {
                currentIndex = (currentIndex + 1) % totalItems;
                updateCarousel();
            }

            function prevSlide() {
                currentIndex = (currentIndex - 1 + totalItems) % totalItems;
                updateCarousel();
            }

            // Auto-rotate every 5 seconds
            setInterval(nextSlide, 5000);

            // Initialize
            updateCarousel();
        </script>
    <?php } ?>

    <!-- ALL PRODUCTS -->
    <h3>📦 All Products</h3>
    <div class="product-grid">
        <?php if ($all_products && $all_products->num_rows > 0) { ?>
            <?php while ($prod = $all_products->fetch_assoc()) { ?>
                <div class="product-card">
                    <img src="../<?= htmlspecialchars($prod['image_url']); ?>" alt="<?= htmlspecialchars($prod['name']); ?>">
                    <h4><?= htmlspecialchars($prod['name']); ?></h4>
                    <p>$<?= number_format($prod['price'], 2); ?></p>
                    <a href="detail-page.php?id=<?= $prod['id']; ?>">View Details</a>
                </div>
            <?php } ?>
        <?php } else { ?>
            <div class="no-products">No products found.</div>
        <?php } ?>
    </div>

</div>

<script>
    // Detect sidebar hover
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
</script>

</body>
</html>