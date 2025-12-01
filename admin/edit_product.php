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

// Get product ID
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($product_id === 0) {
    $_SESSION['error_msg'] = "Invalid product ID";
    header("Location: products.php");
    exit();
}

// Fetch product details
$stmt = $conn->prepare("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

if (!$product) {
    $_SESSION['error_msg'] = "Product not found";
    header("Location: products.php");
    exit();
}

// Fetch all categories
$categories = $conn->query("SELECT * FROM categories ORDER BY name ASC");

// Handle product update
if (isset($_POST['update_product'])) {
    $name = trim($_POST['name']);
    $desc = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $sizes = !empty($_POST['sizes']) ? trim($_POST['sizes']) : '';
    $materials = !empty($_POST['materials']) ? trim($_POST['materials']) : '';
    $brief_details = !empty($_POST['brief_details']) ? trim($_POST['brief_details']) : '';
    
    // Handle colors as JSON - PROPERLY VALIDATE AND ENCODE
    $colors_array = [];
    if (!empty($_POST['selected_colors']) && is_array($_POST['selected_colors'])) {
        foreach ($_POST['selected_colors'] as $color) {
            $color = trim($color);
            // Validate hex format #RRGGBB
            if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                $colors_array[] = $color;
            }
        }
    }
    $colors = json_encode($colors_array); // Convert to JSON string
    
    $image_url = $product['image_url'];

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
                // Delete old image if exists
                if (!empty($product['image_url'])) {
                    $old_path = $_SERVER['DOCUMENT_ROOT'] . '/senior_try/' . $product['image_url'];
                    if (file_exists($old_path)) {
                        unlink($old_path);
                    }
                }
                
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

    // UPDATE with CORRECT bind_param
    // Variables: name, desc, price, image_url, stock, category_id, colors, sizes, materials, brief_details, product_id
    // Types: s, s, d, s, i, i, s, s, s, s, i
    // Total: 11 types for 11 variables
    
    if ($category_id !== null) {
        $stmt = $conn->prepare("UPDATE products SET name = ?, description = ?, price = ?, image_url = ?, stock = ?, category_id = ?, colors = ?, sizes = ?, materials = ?, brief_details = ? WHERE id = ?");
        $stmt->bind_param("ssdsiissssi", $name, $desc, $price, $image_url, $stock, $category_id, $colors, $sizes, $materials, $brief_details, $product_id);
    } else {
        $stmt = $conn->prepare("UPDATE products SET name = ?, description = ?, price = ?, image_url = ?, stock = ?, colors = ?, sizes = ?, materials = ?, brief_details = ? WHERE id = ?");
        $stmt->bind_param("ssdsississi", $name, $desc, $price, $image_url, $stock, $colors, $sizes, $materials, $brief_details, $product_id);
    }
    
    if ($stmt->execute()) {
        $_SESSION['success_msg'] = "✅ Product updated successfully! Colors saved: " . count($colors_array) . " color(s)";
        header("Location: products.php");
        exit();
    } else {
        $_SESSION['error_msg'] = "Error updating product: " . $stmt->error;
    }
    $stmt->close();
}

// Handle product deletion
if (isset($_POST['delete_product'])) {
    // Delete old image
    if (!empty($product['image_url'])) {
        $old_path = $_SERVER['DOCUMENT_ROOT'] . '/senior_try/' . $product['image_url'];
        if (file_exists($old_path)) {
            unlink($old_path);
        }
    }
    
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_msg'] = "✅ Product deleted successfully!";
        header("Location: products.php");
        exit();
    } else {
        $_SESSION['error_msg'] = "Error deleting product: " . $stmt->error;
    }
    $stmt->close();
}

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

// Decode product colors - properly handle all edge cases
$product_colors = [];
if (!empty($product['colors']) && $product['colors'] !== '0' && $product['colors'] !== '[]') {
    $decoded = json_decode($product['colors'], true);
    if (is_array($decoded) && count($decoded) > 0) {
        $product_colors = $decoded;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Product</title>
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

        form textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-row input, .form-row textarea, .form-row select {
            margin-bottom: 0;
        }

        .image-preview {
            margin-bottom: 20px;
            text-align: center;
        }

        .image-preview img {
            max-width: 300px;
            max-height: 300px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
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

        .form-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        form button {
            flex: 1;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            transition: 0.3s;
            font-weight: 600;
            border: none;
            color: white;
        }

        .btn-update {
            background: #007bff;
        }

        .btn-update:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }

        .btn-delete {
            background: #dc3545;
        }

        .btn-delete:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .btn-back {
            background: #6c757d;
            text-decoration: none;
            display: inline-block;
            padding: 10px 20px;
            border-radius: 6px;
            text-align: center;
            margin-bottom: 20px;
            color: white;
        }

        .btn-back:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        small {
            display: block;
            color: #666;
            font-size: 12px;
            margin-top: -8px;
            margin-bottom: 12px;
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

            .form-row {
                grid-template-columns: 1fr;
            }

            .form-buttons {
                flex-direction: column;
            }

            .color-grid {
                grid-template-columns: repeat(auto-fill, minmax(40px, 1fr));
            }

            .color-option {
                width: 40px;
                height: 40px;
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
            <?= $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-error">
            <?= $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?>
        </div>
    <?php endif; ?>

    <a href="products.php" class="btn-back">← Back to Products</a>

    <!-- Edit Product Form -->
    <div class="card-box">
        <h3>✏️ Edit Product</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="text" name="name" placeholder="Product Name" value="<?= htmlspecialchars($product['name']); ?>" required>
            <textarea name="description" placeholder="Full Description"><?= htmlspecialchars($product['description']); ?></textarea>
            
            <div class="form-row">
                <input type="number" name="price" step="0.01" placeholder="Price" value="<?= $product['price']; ?>" required>
                <input type="number" name="stock" placeholder="Stock" value="<?= $product['stock']; ?>" required>
            </div>

            <select name="category_id" required>
                <option value="">Select Category</option>
                <?php
                if ($categories && $categories->num_rows > 0) {
                    $categories->data_seek(0);
                    while($cat = $categories->fetch_assoc()):
                        $selected = ($cat['id'] == $product['category_id']) ? 'selected' : '';
                ?>
                    <option value="<?= $cat['id']; ?>" <?= $selected; ?>><?= htmlspecialchars($cat['name']); ?></option>
                <?php 
                    endwhile;
                }
                ?>
            </select>

            <!-- Color Selector -->
            <div class="color-selector">
                <label for="colors">🎨 Select Colors</label>
                <div class="color-grid" id="colorGrid">
                    <?php foreach ($predefined_colors as $hex => $name): 
                        $is_checked = in_array($hex, $product_colors) ? 'checked' : '';
                    ?>
                        <label class="color-option">
                            <input type="checkbox" name="selected_colors[]" value="<?= $hex; ?>" data-name="<?= $name; ?>" <?= $is_checked; ?>>
                            <div class="color-circle" style="background-color: <?= $hex; ?>;"></div>
                        </label>
                    <?php endforeach; ?>
                </div>
                <label style="margin-bottom: 8px; font-weight: 600; color: #333;">Selected Colors:</label>
                <div class="selected-colors" id="selectedColors">
                    <span style="color: #999; font-style: italic;">No colors selected yet...</span>
                </div>
            </div>

            <div class="form-row">
                <input type="text" name="sizes" placeholder="Sizes (e.g., S, M, L, XL)" value="<?= htmlspecialchars($product['sizes']); ?>">
                <input type="text" name="materials" placeholder="Materials (e.g., Cotton, Polyester)" value="<?= htmlspecialchars($product['materials']); ?>">
            </div>

            <input type="text" name="brief_details" placeholder="Brief Details (e.g., Waterproof, Eco-Friendly)" value="<?= htmlspecialchars($product['brief_details']); ?>">

            <!-- Current Image -->
            <div class="image-preview">
                <label style="font-weight: 600; color: #333; margin-bottom: 10px; display: block;">Current Image:</label>
                <?php 
                    $image_path = !empty($product['image_url']) ? '/senior_try/' . $product['image_url'] : '/senior_try/uploads/default.jpg';
                ?>
                <img src="<?= htmlspecialchars($image_path); ?>" alt="<?= htmlspecialchars($product['name']); ?>" onerror="this.src='/senior_try/uploads/default.jpg'">
            </div>

            <!-- Upload New Image -->
            <input type="file" name="image" accept="image/*">
            <small style="color: #666; display: block; margin-top: -8px; margin-bottom: 12px;">
                ✓ Leave empty to keep current image | Accepts: JPG, PNG, GIF, WEBP, BMP, AVIF, HEIC, SVG, etc. (Max: <?= ini_get('upload_max_filesize'); ?>)
            </small>

            <!-- Form Buttons -->
            <div class="form-buttons">
                <button type="submit" name="update_product" class="btn-update">💾 Update Product</button>
                <button type="submit" name="delete_product" class="btn-delete" onclick="return confirm('Are you sure you want to delete this product? This action cannot be undone.');">🗑️ Delete Product</button>
            </div>
        </form>
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

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateSelectedColors();

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