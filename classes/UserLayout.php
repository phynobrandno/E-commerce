<?php
class UserLayout {

    // Show User Navbar only
    public static function navbar() {
        
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'user') {
            include __DIR__ . '/navbar_user.php';
        } else {
            echo "<!-- No navbar: User is not a standard user -->";
        }
    }

    // Show User Sidebar only
    public static function sidebar() {
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'user') {
            include __DIR__ . '/sidebar_user.php';
        } else {
            echo "<!-- No sidebar: User is not a standard user -->";
        }
    }
}
?>
