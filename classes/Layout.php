<?php
class Layout {

    // Show Admin Navbar only
    public static function navbar() {
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            include __DIR__ . '/navbar_admin.php';
        } else {
            echo "<!-- No navbar: User is not admin -->";
        }
    }

    // Show Admin Sidebar only
    public static function sidebar() {
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            include __DIR__ . '/sidebar_admin.php';
        } else {
            echo "<!-- No sidebar: User is not admin -->";
        }
    }
}
?>
