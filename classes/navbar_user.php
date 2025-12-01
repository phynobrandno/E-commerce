<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$profilePicUrl = 'https://cdn-icons-png.flaticon.com/512/847/847969.png';

if (isset($_SESSION['user_id'])) {
    $dbPath = __DIR__ . '/../config/database.php';
    if (file_exists($dbPath)) {
        require_once $dbPath;
        try {
            $database = new Database();
            $pdo = $database->getConnection();
            $stmt = $pdo->prepare("SELECT profile_pic FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            if ($user && !empty($user['profile_pic'])) {
                $profilePicPath = $user['profile_pic'];
                if (strpos($profilePicPath, 'http') !== 0) {
                    $profilePicPath = '../' . $profilePicPath;
                }
                if (file_exists($profilePicPath)) {
                    $profilePicUrl = $profilePicPath;
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
        <li><a href="dashboard.php">Home</a></li>
        <li><a href="Shop.php">Shop</a></li>
        <li><a href="cart.php">Cart</a></li>
        <li><a href="Orders.php">Orders</a></li>
        <li><a href="Profile.php">Profile</a></li>
    </ul>
    <div class="nav-profile">
        <div class="avatar-container">
            <img src="<?php echo $profilePicUrl; ?>" alt="User Profile" class="avatar">
        </div>
    </div>
</nav>
