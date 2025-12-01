<?php
session_start();

// Include database & layout
require_once __DIR__ . '/../classes/db_connect.php';
require_once __DIR__ . '/../classes/Layout.php';

// Protect admin-only page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Handle product addition
if (isset($_POST['add_product'])) {
    $name = trim($_POST['name']);
    $desc = trim($_POST['description']);
    $price = $_POST['price'];
    $stock = $_POST['stock'];
    $image_url = "";

    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $target_dir = __DIR__ . "/../uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_name = time() . "_" . basename($_FILES["image"]["name"]);
        $target_file = $target_dir . $file_name;

        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $allowed_types = ["jpg", "jpeg", "png", "gif"];

        if (in_array($imageFileType, $allowed_types)) {
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                // ✅ Store relative path accessible to both admin and user
                $image_url = "uploads/" . $file_name;
            }
        }
    }

    $stmt = $conn->prepare("INSERT INTO products (name, description, price, image_url, stock) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssdsi", $name, $desc, $price, $image_url, $stock);
    $stmt->execute();
    $stmt->close();

    header("Location: products.php");
    exit();
}

// Handle product deletion
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $img = $conn->query("SELECT image_url FROM products WHERE id=$id")->fetch_assoc();
    if (!empty($img['image_url']) && file_exists(__DIR__ . "/../" . $img['image_url'])) {
        unlink(__DIR__ . "/../" . $img['image_url']);
    }
    $conn->query("DELETE FROM products WHERE id=$id");
    header("Location: products.php");
    exit();
}

// Fetch all products
$result = $conn->query("SELECT * FROM products ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Products</title>
    <link rel="stylesheet" href="../navbar_sidebar.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #fff;
        }

        .main-content {
            margin-left: 100px;
            width: calc(100% - 100px);
            padding: 30px;
            margin-top: 70px;
            transition: margin-left 0.35s ease-in-out, width 0.35s ease-in-out;
        }

        .main-content.sidebar-expanded {
            margin-left: 230px;
            width: calc(100% - 230px);
        }

        .card-box {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            transition: all 0.35s ease-in-out;
        }

        .card-box h3 {
            margin-bottom: 15px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 8px;
            color: #333;
            font-size: 22px;
        }

        form input, form textarea {
            width: 100%;
            margin-bottom: 12px;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-family: inherit;
        }

        form button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            transition: 0.3s;
            font-weight: 600;
        }

        form button:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            transition: all 0.35s ease-in-out;
        }

        .product-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            overflow: hidden;
            transition: transform 0.3s ease;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
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
            padding: 8px;
            box-sizing: border-box;
            transition: transform 0.3s ease;
        }

        .product-card:hover img {
            transform: scale(1.05);
        }

        .product-card .info {
            padding: 15px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .product-card h4 {
            margin-bottom: 8px;
            font-size: 18px;
            color: #333;
        }

        .product-card p {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-card .price {
            font-weight: bold;
            color: #007bff;
            margin-bottom: 8px;
            font-size: 18px;
        }

        .product-card .stock-info {
            font-size: 13px;
            color: #666;
            margin-bottom: 10px;
        }

        .product-card .delete-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 8px 14px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 13px;
            font-weight: 600;
            transition: 0.3s;
            margin-top: auto;
            text-align: center;
        }

        .product-card .delete-btn:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }

        @media (max-width: 1200px) {
            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            }

            .product-card .image-container {
                height: 180px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 20px;
            }

            .main-content.sidebar-expanded {
                margin-left: 200px;
                width: calc(100% - 200px);
            }

            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
                gap: 15px;
            }

            .product-card .image-container {
                height: 160px;
            }

            .product-card h4 {
                font-size: 16px;
            }

            .product-card p {
                font-size: 12px;
            }

            .product-card .price {
                font-size: 16px;
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

<?php Layout::navbar(); ?>
<?php Layout::sidebar(); ?>

<div class="main-content">
    <div class="card-box">
        <h3>📦 All Products</h3>
        <div class="product-grid">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <div class="product-card">
                        <div class="image-container">
                            <img src="../<?= htmlspecialchars($row['image_url'] ?: 'uploads/default.jpg'); ?>" 
                                alt="<?= htmlspecialchars($row['name']); ?>"
                                onerror="this.src='../uploads/default.jpg'">
                        </div>
                        <div class="info">
                            <h4><?= htmlspecialchars($row['name']); ?></h4>
                            <p><?= htmlspecialchars($row['description']); ?></p>
                            <div class="price">$<?= number_format($row['price'], 2); ?></div>
                            <small class="stock-info">📦 Stock: <?= htmlspecialchars($row['stock']); ?></small>
                            <a href="?delete=<?= $row['id']; ?>" 
                                onclick="return confirm('Delete this product?');" 
                                class="delete-btn">
                                🗑️ Delete
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #999;">
                    <p style="font-size: 18px;">No products found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Detect sidebar hover
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
</script>

</body>
</html>