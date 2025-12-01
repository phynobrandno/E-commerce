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

$success_message = '';
$error_message = '';

// Handle add category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add') {
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        
        if (empty($name)) {
            $error_message = "Category name is required.";
        } else {
            try {
                // Check if category already exists
                $check_stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
                $check_stmt->execute([$name]);
                
                if ($check_stmt->fetch()) {
                    $error_message = "Category already exists.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
                    $stmt->execute([$name]);
                    $success_message = "Category added successfully!";
                }
            } catch (PDOException $e) {
                $error_message = "Error adding category: " . $e->getMessage();
            }
        }
    }
    
    // Handle update category
    elseif ($action === 'update') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        
        if ($id <= 0 || empty($name)) {
            $error_message = "Invalid category ID or name.";
        } else {
            try {
                // Check if new name already exists (excluding current category)
                $check_stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
                $check_stmt->execute([$name, $id]);
                
                if ($check_stmt->fetch()) {
                    $error_message = "Category name already exists.";
                } else {
                    $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?");
                    $stmt->execute([$name, $id]);
                    $success_message = "Category updated successfully!";
                }
            } catch (PDOException $e) {
                $error_message = "Error updating category: " . $e->getMessage();
            }
        }
    }
    
    // Handle delete category
    elseif ($action === 'delete') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if ($id <= 0) {
            $error_message = "Invalid category ID.";
        } else {
            try {
                // Check if category has products
                $check_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
                $check_stmt->execute([$id]);
                $result = $check_stmt->fetch();
                
                if ($result['count'] > 0) {
                    $error_message = "Cannot delete category with products. Please delete or reassign products first.";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                    $stmt->execute([$id]);
                    $success_message = "Category deleted successfully!";
                }
            } catch (PDOException $e) {
                $error_message = "Error deleting category: " . $e->getMessage();
            }
        }
    }
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get total records for pagination
try {
    if (!empty($search)) {
        $count_sql = "SELECT COUNT(*) as total FROM categories WHERE name LIKE ?";
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute(["%$search%"]);
    } else {
        $count_sql = "SELECT COUNT(*) as total FROM categories";
        $count_stmt = $pdo->query($count_sql);
    }
    $total_records = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    $error_message = "Error counting records: " . $e->getMessage();
    $total_pages = 1;
}

// Fetch categories
try {
    if (!empty($search)) {
        $sql = "SELECT 
                    c.id,
                    c.name,
                    COUNT(p.id) as product_count
                FROM categories c
                LEFT JOIN products p ON c.id = p.category_id
                WHERE c.name LIKE ?
                GROUP BY c.id
                ORDER BY c.name ASC
                LIMIT $records_per_page OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(["%$search%"]);
    } else {
        $sql = "SELECT 
                    c.id,
                    c.name,
                    COUNT(p.id) as product_count
                FROM categories c
                LEFT JOIN products p ON c.id = p.category_id
                GROUP BY c.id
                ORDER BY c.name ASC
                LIMIT $records_per_page OFFSET $offset";
        $stmt = $pdo->query($sql);
    }
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error fetching categories: " . $e->getMessage();
    $categories = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Admin</title>
    <link rel="stylesheet" href="../navbar_sidebar.css">
    <style>
        .content {
            margin-left: 100px;
            width: 90%;
            padding: 20px;
            margin-top: 60px;
        }

        .categories-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .header-section h1 {
            margin: 0;
            color: #333;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s;
        }

        .btn-primary {
            background-color: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background-color: #0056b3;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        .btn-success {
            background-color: #28a745;
            color: white;
        }

        .btn-success:hover {
            background-color: #218838;
        }

        .btn-warning {
            background-color: #ffc107;
            color: #333;
            font-size: 12px;
            padding: 6px 12px;
        }

        .btn-warning:hover {
            background-color: #e0a800;
        }

        .btn-danger {
            background-color: #dc3545;
            color: white;
            font-size: 12px;
            padding: 6px 12px;
        }

        .btn-danger:hover {
            background-color: #c82333;
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

        .search-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .search-form {
            display: flex;
            gap: 10px;
            align-items: end;
        }

        .search-group {
            flex: 1;
            min-width: 250px;
        }

        .search-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
            font-size: 14px;
        }

        .search-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .form-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .form-card h3 {
            margin-top: 0;
            color: #333;
            font-size: 16px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .form-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .categories-table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .categories-table {
            width: 100%;
            border-collapse: collapse;
        }

        .categories-table thead {
            background-color: #f8f9fa;
        }

        .categories-table th {
            padding: 15px;
            text-align: left;
            font-weight: bold;
            color: #333;
            border-bottom: 2px solid #dee2e6;
            font-size: 14px;
        }

        .categories-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #dee2e6;
            font-size: 14px;
        }

        .categories-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            padding: 20px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }

        .pagination a:hover {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }

        .pagination .active {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }

        .no-categories {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .modal-header h2 {
            margin: 0 0 20px 0;
            color: #333;
        }

        .close {
            float: right;
            font-size: 28px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
        }

        .close:hover {
            color: #000;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
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
            font-size: 13px;
            text-transform: uppercase;
        }

        .stat-card .stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #333;
        }

        @media (max-width: 768px) {
            .content {
                margin-left: 0;
                padding: 10px;
            }

            .header-section {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .search-form {
                flex-direction: column;
            }

            .search-group {
                width: 100%;
                min-width: auto;
            }

            .action-buttons {
                flex-direction: column;
            }

            .categories-table {
                font-size: 12px;
            }

            .categories-table th,
            .categories-table td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>

<?php Layout::navbar(); ?>
<?php Layout::sidebar(); ?>

<div class="content">
    <div class="categories-container">
        <div class="header-section">
            <h1>Categories Management</h1>
            <button onclick="openAddModal()" class="btn btn-primary">+ Add Category</button>
        </div>

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

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Categories</h3>
                <div class="stat-number"><?php echo $total_records; ?></div>
            </div>
            <div class="stat-card">
                <h3>Current Page</h3>
                <div class="stat-number"><?php echo $page; ?> / <?php echo $total_pages; ?></div>
            </div>
        </div>

        <!-- Search -->
        <div class="search-section">
            <form method="GET" action="" class="search-form">
                <div class="search-group">
                    <label for="search">Search Category</label>
                    <input type="text" id="search" name="search" 
                           placeholder="Search by category name..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="categories.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>

        <!-- Categories Table -->
        <div class="categories-table-container">
            <?php if (empty($categories)): ?>
                <div class="no-categories">
                    <h3>No Categories Found</h3>
                    <p>There are no categories to display. <?php echo $search ? 'Try a different search.' : 'Start by adding a new category.'; ?></p>
                </div>
            <?php else: ?>
                <table class="categories-table">
                    <thead>
                        <tr>
                            <th>Category ID</th>
                            <th>Category Name</th>
                            <th>Products</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($category['id']); ?></td>
                                <td><?php echo htmlspecialchars($category['name']); ?></td>
                                <td>
                                    <span style="background-color: #e7f3ff; color: #004085; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                                        <?php echo htmlspecialchars($category['product_count']); ?> product(s)
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button onclick="openEditModal(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>')" 
                                                class="btn btn-warning">Edit</button>
                                        <button onclick="confirmDelete(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>')" 
                                                class="btn btn-danger">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                        <?php endif; ?>

                        <?php 
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1): ?>
                            <a href="?page=1&search=<?php echo urlencode($search); ?>">1</a>
                            <?php if ($start_page > 2): ?>
                                <span>...</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <span>...</span>
                            <?php endif; ?>
                            <a href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>"><?php echo $total_pages; ?></a>
                        <?php endif; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeAddModal()">&times;</span>
        <div class="modal-header">
            <h2>Add New Category</h2>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label for="add_name">Category Name</label>
                <input type="text" id="add_name" name="name" required 
                       placeholder="Enter category name" autocomplete="off">
            </div>
            <div class="modal-buttons">
                <button type="submit" class="btn btn-primary">Add Category</button>
                <button type="button" onclick="closeAddModal()" class="btn btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Category Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeEditModal()">&times;</span>
        <div class="modal-header">
            <h2>Edit Category</h2>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="update">
            <input type="hidden" id="edit_id" name="id">
            <div class="form-group">
                <label for="edit_name">Category Name</label>
                <input type="text" id="edit_name" name="name" required 
                       placeholder="Enter category name" autocomplete="off">
            </div>
            <div class="modal-buttons">
                <button type="submit" class="btn btn-primary">Update Category</button>
                <button type="button" onclick="closeEditModal()" class="btn btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Category Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeDeleteModal()">&times;</span>
        <div class="modal-header">
            <h2>Delete Category</h2>
        </div>
        <p>Are you sure you want to delete the category "<strong id="delete_name"></strong>"?</p>
        <p style="color: #999; font-size: 12px;">This action cannot be undone if there are no products in this category.</p>
        <form method="POST" action="">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" id="delete_id" name="id">
            <div class="modal-buttons">
                <button type="submit" class="btn btn-danger">Delete</button>
                <button type="button" onclick="closeDeleteModal()" class="btn btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
// Add Modal
function openAddModal() {
    document.getElementById('addModal').style.display = 'block';
    document.getElementById('add_name').value = '';
}

function closeAddModal() {
    document.getElementById('addModal').style.display = 'none';
}

// Edit Modal
function openEditModal(id, name) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('editModal').style.display = 'block';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// Delete Modal
function confirmDelete(id, name) {
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_name').textContent = name;
    document.getElementById('deleteModal').style.display = 'block';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const addModal = document.getElementById('addModal');
    const editModal = document.getElementById('editModal');
    const deleteModal = document.getElementById('deleteModal');
    
    if (event.target == addModal) addModal.style.display = 'none';
    if (event.target == editModal) editModal.style.display = 'none';
    if (event.target == deleteModal) deleteModal.style.display = 'none';
}

// Close modal on ESC key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        document.getElementById('addModal').style.display = 'none';
        document.getElementById('editModal').style.display = 'none';
        document.getElementById('deleteModal').style.display = 'none';
    }
});
</script>

</body>
</html>