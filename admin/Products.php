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

// --- Handle category addition ---
if (isset($_POST['add_category'])) {
    $cat_name = trim($_POST['category_name']);
    if ($cat_name !== "") {
        $stmt = $conn->prepare("INSERT IGNORE INTO categories (name) VALUES (?)");
        $stmt->bind_param("s", $cat_name);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success_msg'] = "Category added successfully!";
        header("Location: products.php");
        exit();
    }
}

// --- Handle product addition ---
if (isset($_POST['add_product'])) {
    $name = trim($_POST['name']);
    $desc = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $sizes = !empty($_POST['sizes']) ? trim($_POST['sizes']) : '';
    $materials = !empty($_POST['materials']) ? trim($_POST['materials']) : '';
    $brief_details = !empty($_POST['brief_details']) ? trim($_POST['brief_details']) : '';
    
    // Handle colors as JSON - PROPERLY CONVERT AND VALIDATE
    $colors = [];
    if (!empty($_POST['selected_colors']) && is_array($_POST['selected_colors'])) {
        foreach ($_POST['selected_colors'] as $color) {
            $color = trim($color);
            // Validate hex format #RRGGBB
            if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                $colors[] = $color;
            }
        }
    }
    $colors_json = json_encode($colors); // Convert to JSON string
    
    $image_url = "";

    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/senior_try/uploads/';
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_tmp = $_FILES['image']['tmp_name'];
        $file_name = $_FILES['image']['name'];
        $file_type = $_FILES['image']['type'];
        
        if (!file_exists($file_tmp)) {
            $_SESSION['error_msg'] = "Temp file not found. Check php.ini upload_tmp_dir setting.";
        } else {
            $allowed_types = [
                'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 
                'image/webp', 'image/bmp', 'image/svg+xml', 'image/tiff',
                'image/avif', 'image/heic', 'image/heif', 'image/x-icon'
            ];
            
            $is_valid_image = false;
            
            if (in_array($file_type, $allowed_types)) {
                $is_valid_image = true;
            }
            
            $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'tiff', 'tif', 'avif', 'heic', 'heif', 'ico'];
            if (in_array($extension, $allowed_extensions)) {
                $is_valid_image = true;
            }
            
            $image_info = @getimagesize($file_tmp);
            if ($image_info !== false) {
                $is_valid_image = true;
            }
            
            if ($is_valid_image) {
                $new_filename = time() . '_' . uniqid() . '.' . $extension;
                $target_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($file_tmp, $target_path)) {
                    $image_url = 'uploads/' . $new_filename;
                    @chmod($target_path, 0644);
                } else {
                    $_SESSION['error_msg'] = "Failed to move uploaded file.";
                }
            } else {
                $_SESSION['error_msg'] = "Invalid image file.";
            }
        }
    } else {
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE => 'File too large (php.ini limit)',
                UPLOAD_ERR_FORM_SIZE => 'File too large (form limit)',
                UPLOAD_ERR_PARTIAL => 'File partially uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'No temp directory',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write to disk',
                UPLOAD_ERR_EXTENSION => 'PHP extension stopped upload'
            ];
            $_SESSION['error_msg'] = $upload_errors[$_FILES['image']['error']] ?? 'Unknown upload error';
        }
    }

    // INSERT with CORRECT bind_param - 10 types for 10 variables
    // s = name (string)
    // s = description (string)
    // d = price (double)
    // s = image_url (string)
    // i = stock (integer)
    // i = category_id (integer)
    // s = colors_json (string - JSON)
    // s = sizes (string)
    // s = materials (string)
    // s = brief_details (string)
    
    if ($category_id !== null) {
        $stmt = $conn->prepare("INSERT INTO products (name, description, price, image_url, stock, category_id, colors, sizes, materials, brief_details) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdsiissss", $name, $desc, $price, $image_url, $stock, $category_id, $colors_json, $sizes, $materials, $brief_details);
    } else {
        $stmt = $conn->prepare("INSERT INTO products (name, description, price, image_url, stock, colors, sizes, materials, brief_details) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sdssisss", $name, $desc, $price, $image_url, $stock, $colors_json, $sizes, $materials, $brief_details);
    }
    
    if ($stmt->execute()) {
        $_SESSION['success_msg'] = "✅ Product added successfully! Colors saved: " . count($colors) . " color(s)";
    } else {
        $_SESSION['error_msg'] = "❌ Error adding product: " . $stmt->error;
    }
    $stmt->close();

    header("Location: products.php");
    exit();
}

// Fetch categories & products
$categories = $conn->query("SELECT * FROM categories ORDER BY name ASC");
$result = $conn->query("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC");

// Predefined colors
$predefined_colors = [
    '#FF0000' => 'Red',
    '#00FF00' => 'Green',
    '#0000FF' => 'Blue',
    '#FFFF00' => 'Yellow',
    '#FFA500' => 'Orange',
    '#800080' => 'Purple',
    '#FFC0CB' => 'Pink',
    '#000000' => 'Black',
    '#FFFFFF' => 'White',
    '#808080' => 'Gray',
    '#A52A2A' => 'Brown',
    '#00FFFF' => 'Cyan'
];
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
            margin-top: 70px;
            padding: 30px;
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

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        form input, form textarea, form select {
            width: 100%;
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-family: inherit;
            font-size: 14px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-row input, .form-row textarea, .form-row select {
            margin-bottom: 0;
        }

        .color-selector {
            margin-bottom: 15px;
        }

        .color-selector label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #333;
        }

        .color-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(50px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }

        .color-option {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 3px solid #ddd;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .color-option:hover {
            transform: scale(1.1);
            border-color: #333;
        }

        .color-option input {
            display: none;
        }

        .color-option input:checked + .color-circle::after {
            content: "✓";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 24px;
            font-weight: bold;
            text-shadow: 0 0 3px rgba(0,0,0,0.5);
        }

        .color-circle {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            position: relative;
        }

        .selected-colors {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 15px;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            min-height: 50px;
        }

        .selected-colors.empty {
            display: flex;
            align-items: center;
            color: #999;
            font-style: italic;
        }

        .color-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            background: white;
            padding: 8px 12px;
            border-radius: 20px;
            border: 1px solid #ddd;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .color-badge-circle {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: 2px solid #ddd;
        }

        .color-badge-name {
            font-size: 13px;
            color: #333;
        }

        .color-badge-remove {
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            padding: 0;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            transition: 0.2s;
        }

        .color-badge-remove:hover {
            background: #c0392b;
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
            width: 100%;
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
            flex: 1;
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
            margin-bottom: 4px;
        }

        .product-card .category {
            font-size: 12px;
            color: #999;
            margin-bottom: 10px;
        }

        .product-colors {
            display: flex;
            gap: 6px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }

        .product-color-circle {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 2px solid #ddd;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .product-attributes {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
            font-size: 12px;
        }

        .product-attributes p {
            margin-bottom: 4px;
            color: #555;
        }

        .product-attributes strong {
            color: #333;
        }

        .product-card .edit-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 14px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 13px;
            font-weight: 600;
            transition: 0.3s;
            text-align: center;
            width: 100%;
        }

        .product-card .edit-btn:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .product-card .button-group {
            display: flex;
            gap: 8px;
            margin-top: auto;
        }

        .no-products {
            grid-column: 1 / -1;
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 16px;
        }

        small {
            display: block;
            color: #666;
            font-size: 12px;
            margin-top: -8px;
            margin-bottom: 12px;
        }

        @media (max-width: 1200px) {
            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            }

            .product-card .image-container {
                height: 180px;
            }

            .form-row {
                grid-template-columns: 1fr;
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

            .color-grid {
                grid-template-columns: repeat(auto-fill, minmax(40px, 1fr));
            }

            .color-option {
                width: 40px;
                height: 40px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }

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
    
    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success">
            ✅ <?= $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-error">
            ❌ <?= $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?>
        </div>
    <?php endif; ?>
    
    <!-- Add Category -->
    <div class="card-box">
        <h3>➕ Add Category</h3>
        <form method="POST">
            <input type="text" name="category_name" placeholder="Category Name" required>
            <button type="submit" name="add_category">Add Category</button>
        </form>
    </div>

    <!-- Add Product -->
    <div class="card-box">
        <h3>➕ Add New Product</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="text" name="name" placeholder="Product Name" required>
            <textarea name="description" placeholder="Full Description" rows="3"></textarea>
            
            <div class="form-row">
                <input type="number" name="price" step="0.01" placeholder="Price" required>
                <input type="number" name="stock" placeholder="Stock" required>
            </div>

            <select name="category_id" required>
                <option value="">Select Category</option>
                <?php
                if ($categories && $categories->num_rows > 0) {
                    $categories->data_seek(0);
                    while($cat = $categories->fetch_assoc()):
                ?>
                    <option value="<?= $cat['id']; ?>"><?= htmlspecialchars($cat['name']); ?></option>
                <?php 
                    endwhile;
                }
                ?>
            </select>

            <!-- Color Selector -->
            <div class="color-selector">
                <label for="colors">🎨 Select Colors</label>
                <div class="color-grid" id="colorGrid">
                    <?php foreach ($predefined_colors as $hex => $name): ?>
                        <label class="color-option">
                            <input type="checkbox" name="selected_colors[]" value="<?= $hex; ?>" data-name="<?= $name; ?>">
                            <div class="color-circle" style="background-color: <?= $hex; ?>;"></div>
                        </label>
                    <?php endforeach; ?>
                </div>
                <label style="margin-bottom: 8px; font-weight: 600; color: #333;">Selected Colors:</label>
                <div class="selected-colors empty" id="selectedColors">
                    <span style="color: #999; font-style: italic;">No colors selected yet...</span>
                </div>
            </div>

            <div class="form-row">
                <input type="text" name="sizes" placeholder="Sizes (e.g., S, M, L, XL)">
                <input type="text" name="materials" placeholder="Materials (e.g., Cotton, Polyester)">
            </div>

            <input type="text" name="brief_details" placeholder="Brief Details (e.g., Waterproof, Eco-Friendly)">

            <input type="file" name="image" accept="image/*" required>
            <small style="color: #666; display: block; margin-top: -8px; margin-bottom: 12px;">
                ✓ Accepts: JPG, PNG, GIF, WEBP, BMP, AVIF, HEIC, SVG, etc. (Max: <?= ini_get('upload_max_filesize'); ?>)
            </small>
            <button type="submit" name="add_product">Add Product</button>
        </form>
    </div>

    <!-- Display Products -->
    <div class="card-box">
        <h3>📦 All Products</h3>
        <div class="product-grid">
            <?php 
            if ($result && $result->num_rows > 0):
                while($row = $result->fetch_assoc()): 
                    $image_path = !empty($row['image_url']) ? '/senior_try/' . $row['image_url'] : '/senior_try/uploads/default.jpg';
                    $colors = !empty($row['colors']) ? json_decode($row['colors'], true) : [];
            ?>
                <div class="product-card">
                    <div class="image-container">
                        <img src="<?= htmlspecialchars($image_path); ?>" 
                            alt="<?= htmlspecialchars($row['name']); ?>"
                            onerror="this.src='/senior_try/uploads/default.jpg'">
                    </div>
                    <div class="info">
                        <h4><?= htmlspecialchars($row['name']); ?></h4>
                        <p><?= htmlspecialchars($row['description']); ?></p>
                        <div class="price">$<?= number_format($row['price'], 2); ?></div>
                        <small class="stock-info">📦 Stock: <?= htmlspecialchars($row['stock']); ?></small>
                        <small class="category">📁 <?= htmlspecialchars($row['category_name'] ?: 'Uncategorized'); ?></small>
                        
                        <?php if (!empty($colors) && is_array($colors)): ?>
                            <div class="product-colors">
                                <?php foreach ($colors as $color): ?>
                                    <div class="product-color-circle" style="background-color: <?= htmlspecialchars($color); ?>;" title="<?= htmlspecialchars($color); ?>"></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($row['sizes']) || !empty($row['materials']) || !empty($row['brief_details'])): ?>
                            <div class="product-attributes">
                                <?php if (!empty($row['sizes'])): ?>
                                    <p><strong>Sizes:</strong> <?= htmlspecialchars($row['sizes']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($row['materials'])): ?>
                                    <p><strong>Materials:</strong> <?= htmlspecialchars($row['materials']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($row['brief_details'])): ?>
                                    <p><strong>Details:</strong> <?= htmlspecialchars($row['brief_details']); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="button-group">
                            <a href="edit_product.php?id=<?= $row['id']; ?>" class="edit-btn">
                                ✏️ Edit Product
                            </a>
                        </div>
                    </div>
                </div>
            <?php 
                endwhile;
            else:
            ?>
                <div class="no-products">
                    <p>📦 No products yet. Add your first product above!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
    // Color selector functionality
    const predefinedColors = <?= json_encode($predefined_colors); ?>;
    
    document.querySelectorAll('.color-option input').forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedColors);
    });

    function updateSelectedColors() {
        const selectedDiv = document.getElementById('selectedColors');
        const checkedBoxes = document.querySelectorAll('.color-option input:checked');
        
        if (checkedBoxes.length === 0) {
            selectedDiv.innerHTML = '<span style="color: #999; font-style: italic;">No colors selected yet...</span>';
            selectedDiv.classList.add('empty');
            return;
        }

        selectedDiv.classList.remove('empty');
        selectedDiv.innerHTML = '';

        checkedBoxes.forEach(checkbox => {
            const colorHex = checkbox.value;
            const colorName = checkbox.dataset.name;
            
            const badge = document.createElement('div');
            badge.className = 'color-badge';
            badge.innerHTML = `
                <div class="color-badge-circle" style="background-color: ${colorHex};"></div>
                <span class="color-badge-name">${colorName}</span>
                <button type="button" class="color-badge-remove" onclick="this.closest('.color-badge').remove(); updateSelectedColors();">×</button>
            `;
            selectedDiv.appendChild(badge);
        });
    }

    // Sidebar hover
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