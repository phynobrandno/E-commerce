<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$profilePicUrl = 'https://cdn-icons-png.flaticon.com/512/847/847969.png';
$userName = 'User';

if (isset($_SESSION['user_id'])) {
    $dbPath = __DIR__ . '/../config/database.php';
    if (file_exists($dbPath)) {
        require_once $dbPath;
        try {
            $database = new Database();
            $pdo = $database->getConnection();
            $stmt = $pdo->prepare("SELECT username, profile_pic FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            if ($user) {
                $userName = htmlspecialchars($user['username']);
                if (!empty($user['profile_pic'])) {
                    $profilePicPath = $user['profile_pic'];
                    if (strpos($profilePicPath, 'http') !== 0) {
                        $profilePicPath = '../' . $profilePicPath;
                    }
                    if (file_exists($profilePicPath)) {
                        $profilePicUrl = $profilePicPath;
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Error: " . $e->getMessage());
        }
    }
}
?>

<nav class="navbar">
    <div class="nav-logo">🛍️ GCO Limited </div>
    <ul class="nav-links">
        <li><a href="Overview.php" data-short="Overview">Dashboard Overview</a></li>
        <li><a href="ALL_product.php"><i class="fas fa-box"></i> All Products</a></li>
        <li><a href="Categories.php"><i class="fas fa-tags"></i> Categories</a></li>
        <li><a href="view_orders.php"><i class="fas fa-users"></i> View Orders</a></li>
        <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
    </ul>
    <div class="nav-profile">
        <div class="avatar-container">
            <img src="<?php echo $profilePicUrl; ?>" alt="<?php echo $userName; ?>" class="avatar" title="<?php echo $userName; ?>">
        </div>
        <span><?php echo strlen($userName) > 15 ? substr($userName, 0, 15) . '...' : $userName; ?></span>
    </div>
</nav>