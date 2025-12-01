<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Upload Directory Test</h2>";

$upload_dir = __DIR__ . '/../uploads/receipts/';

echo "Upload directory path: " . $upload_dir . "<br>";
echo "Directory exists: " . (file_exists($upload_dir) ? 'YES' : 'NO') . "<br>";
echo "Is directory: " . (is_dir($upload_dir) ? 'YES' : 'NO') . "<br>";
echo "Is readable: " . (is_readable($upload_dir) ? 'YES' : 'NO') . "<br>";
echo "Is writable: " . (is_writable($upload_dir) ? 'YES' : 'NO') . "<br>";

if (is_dir($upload_dir)) {
    echo "Directory permissions: " . substr(sprintf('%o', fileperms($upload_dir)), -4) . "<br>";
    
    // Try to create a test file
    $test_file = $upload_dir . 'test_' . time() . '.txt';
    if (file_put_contents($test_file, 'test content')) {
        echo "<br><strong style='color:green'>✅ SUCCESS: Can write to directory!</strong><br>";
        echo "Test file created: " . $test_file . "<br>";
        
        // Clean up
        unlink($test_file);
        echo "Test file deleted<br>";
    } else {
        echo "<br><strong style='color:red'>❌ FAILED: Cannot write to directory!</strong><br>";
    }
}

echo "<br><h3>Files in directory:</h3>";
if (is_dir($upload_dir)) {
    $files = scandir($upload_dir);
    echo "<pre>";
    print_r($files);
    echo "</pre>";
}

// Check database connection
require_once __DIR__ . '/../classes/db_connect.php';
echo "<h3>Database Test:</h3>";
$result = $conn->query("SELECT COUNT(*) as count FROM payments");
$row = $result->fetch_assoc();
echo "Payments in database: " . $row['count'] . "<br>";

$result2 = $conn->query("SELECT COUNT(*) as count FROM orders");
$row2 = $result2->fetch_assoc();
echo "Orders in database: " . $row2['count'] . "<br>";
?>