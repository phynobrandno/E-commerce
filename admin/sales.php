<?php
session_start();

// Include Layout class
include "../classes/Layout.php";

// Protect page: only admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../navbar_sidebar.css">
</head>
<body>

<?php Layout::navbar(); ?>
<?php Layout::sidebar(); ?>

<div class="content">
    <h1>Welcome, <?php echo htmlspecialchars($_SESSION['role']); ?></h1>
</div>

</body>
</html>
