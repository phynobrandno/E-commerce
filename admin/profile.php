<?php
session_start();

// Include database & layout
require_once __DIR__ . '/../classes/db_connect.php';
require_once __DIR__ . '/../classes/Layout.php';

// Protect page: only admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // Update profile
    if ($_POST['action'] === 'update_profile') {
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        $address = isset($_POST['address']) ? trim($_POST['address']) : '';
        
        if (empty($username) || empty($email)) {
            echo json_encode(['success' => false, 'message' => 'Username and email are required']);
            exit();
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email format']);
            exit();
        }
        
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            exit();
        }
        
        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, phone = ?, address = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $username, $email, $phone, $address, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['username'] = $username;
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
        }
        exit();
    }
    
    // Change password
    if ($_POST['action'] === 'change_password') {
        $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
        $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        
        // Get current password hash
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (!password_verify($current_password, $user['password'])) {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
            exit();
        }
        
        if (strlen($new_password) < 6) {
            echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters']);
            exit();
        }
        
        if ($new_password !== $confirm_password) {
            echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
            exit();
        }
        
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to change password']);
        }
        exit();
    }
    
    // Update profile picture
    if ($_POST['action'] === 'update_profile_pic') {
        if (!isset($_FILES['profile_pic'])) {
            echo json_encode(['success' => false, 'message' => 'No file uploaded']);
            exit();
        }
        
        $targetDir = "../uploads/profiles/";
        
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        
        $imageFileType = strtolower(pathinfo($_FILES["profile_pic"]["name"], PATHINFO_EXTENSION));
        $newFileName = "user_" . $user_id . "_" . time() . "." . $imageFileType;
        $targetFile = $targetDir . $newFileName;
        
        // Validate file
        $check = getimagesize($_FILES["profile_pic"]["tmp_name"]);
        if ($check === false) {
            echo json_encode(['success' => false, 'message' => 'File is not an image']);
            exit();
        }
        
        if ($_FILES["profile_pic"]["size"] > 5000000) {
            echo json_encode(['success' => false, 'message' => 'File is too large. Maximum size is 5MB']);
            exit();
        }
        
        if (!in_array($imageFileType, ["jpg", "jpeg", "png", "gif", "webp"])) {
            echo json_encode(['success' => false, 'message' => 'Only JPG, JPEG, PNG, GIF & WEBP files are allowed']);
            exit();
        }
        
        // Move uploaded file
        if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $targetFile)) {
            // Get old profile picture to delete
            $stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $userData = $result->fetch_assoc();
            
            if ($userData && !empty($userData['profile_pic'])) {
                $oldPicPath = "../" . $userData['profile_pic'];
                if (file_exists($oldPicPath)) {
                    unlink($oldPicPath);
                }
            }
            
            // Update database with relative path
            $relativePath = "uploads/profiles/" . $newFileName;
            $stmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
            $stmt->bind_param("si", $relativePath, $user_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Profile picture updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database update failed: ' . $conn->error]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Error uploading file']);
        }
        exit();
    }
}

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get profile picture
$profilePicPath = '../assets/default-avatar.svg';
if (!empty($user['profile_pic'])) {
    $fullPath = '../' . htmlspecialchars($user['profile_pic']);
    if (file_exists($fullPath)) {
        $profilePicPath = $fullPath;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Profile</title>
    <link rel="stylesheet" href="../navbar_sidebar.css">
    <style>
        .content {
            margin-left: 100px;
            padding: 30px;
            max-width: 1600px;
            width: 100%;
            box-sizing: border-box;
        }

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 20px rgba(102,126,234,0.3);
            margin-top: 70px;
        }

        .page-header h1 {
            margin: 0 0 10px;
            font-size: 32px;
        }

        .page-header p {
            margin: 0;
            opacity: 0.9;
        }

        .profile-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .profile-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 5px solid #667eea;
        }

        .profile-pic-container {
            position: relative;
            width: 180px;
            height: 180px;
            margin: 0 auto 20px;
        }

        .profile-pic {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #667eea;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .profile-pic-overlay {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: white;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 3px 8px rgba(0,0,0,0.2);
            transition: all 0.3s;
            border: 3px solid #667eea;
        }

        .profile-pic-overlay:hover {
            transform: scale(1.15);
            background: #f0f0f0;
        }

        .profile-pic-overlay svg {
            width: 24px;
            height: 24px;
            fill: #667eea;
        }

        #profile-pic-input {
            display: none;
        }

        .profile-info h2 {
            margin: 20px 0 15px;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #666;
        }

        .info-value {
            color: #333;
        }

        .form-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 5px solid #667eea;
            margin-bottom: 30px;
        }

        .form-section h2 {
            margin: 0 0 20px;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 8px;
            color: #555;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input,
        .form-group textarea {
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            grid-column: 1 / -1;
        }

        .save-btn, .reset-btn {
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .save-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            flex: 1;
        }

        .save-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(102, 126, 234, 0.4);
        }

        .reset-btn {
            background: #6c757d;
            color: white;
            flex: 1;
        }

        .reset-btn:hover {
            background: #5a6268;
        }

        .notification {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 9999;
            padding: 15px 25px;
            border-radius: 8px;
            color: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            animation: slideIn 0.3s ease;
        }

        .notification.success {
            background: #28a745;
        }

        .notification.error {
            background: #dc3545;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @media (max-width: 900px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<?php Layout::navbar(); ?>
<?php Layout::sidebar(); ?>

<div class="content">
    <div class="page-header">
        <h1>👤 Admin Profile</h1>
        <p>Manage your profile information and security settings</p>
    </div>

    <div class="profile-container">
        <!-- Profile Picture & Info Card -->
        <div class="profile-card">
            <div class="profile-pic-container">
                <img src="<?php echo $profilePicPath; ?>" 
                     alt="Profile Picture" 
                     class="profile-pic"
                     id="profile-pic-preview"
                     onerror="this.src='../assets/default-avatar.svg'">
                <form enctype="multipart/form-data" id="profile-pic-form">
                    <label for="profile-pic-input" class="profile-pic-overlay" title="Change Profile Picture">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                        </svg>
                    </label>
                    <input type="file" 
                           name="profile_pic" 
                           id="profile-pic-input" 
                           accept="image/*">
                </form>
            </div>

            <div class="profile-info">
                <h2>Account Information</h2>
                <div class="info-row">
                    <span class="info-label">Name:</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['username']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['email']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Role:</span>
                    <span class="info-value"><strong><?php echo ucfirst($user['role']); ?></strong></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Member Since:</span>
                    <span class="info-value"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></span>
                </div>
            </div>
        </div>

        <!-- Forms Section -->
        <div>
            <!-- Update Profile Form -->
            <div class="form-section">
                <h2>Edit Profile</h2>
                <form id="profileForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" 
                                   id="username" 
                                   name="username" 
                                   value="<?php echo htmlspecialchars($user['username']); ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" 
                                   id="phone" 
                                   name="phone" 
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Address</label>
                            <input type="text" 
                                   id="address" 
                                   name="address" 
                                   value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                        </div>

                        <div class="button-group">
                            <button type="button" class="save-btn" onclick="updateProfile()">Save Changes</button>
                            <button type="reset" class="reset-btn">Reset</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Change Password Form -->
            <div class="form-section">
                <h2>Change Password</h2>
                <form id="passwordForm">
                    <div class="form-grid">
                        <div class="form-group full">
                            <label for="current_password">Current Password</label>
                            <input type="password" 
                                   id="current_password" 
                                   name="current_password" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" 
                                   id="new_password" 
                                   name="new_password" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <input type="password" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   required>
                        </div>

                        <div class="button-group">
                            <button type="button" class="save-btn" onclick="changePassword()">Change Password</button>
                            <button type="reset" class="reset-btn">Reset</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

function updateProfile() {
    const formData = new FormData();
    formData.append('action', 'update_profile');
    formData.append('username', document.getElementById('username').value);
    formData.append('email', document.getElementById('email').value);
    formData.append('phone', document.getElementById('phone').value);
    formData.append('address', document.getElementById('address').value);
    
    fetch('profile.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error updating profile', 'error');
    });
}

function changePassword() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword !== confirmPassword) {
        showNotification('Passwords do not match', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'change_password');
    formData.append('current_password', document.getElementById('current_password').value);
    formData.append('new_password', newPassword);
    formData.append('confirm_password', confirmPassword);
    
    fetch('profile.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            document.getElementById('passwordForm').reset();
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error changing password', 'error');
    });
}

document.getElementById('profile-pic-input').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('profile-pic-preview').src = e.target.result;
    }
    reader.readAsDataURL(file);
    
    // Upload the image
    const formData = new FormData();
    formData.append('action', 'update_profile_pic');
    formData.append('profile_pic', file);
    
    fetch('profile.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        showNotification(data.message, data.success ? 'success' : 'error');
        if (data.success) {
            setTimeout(() => location.reload(), 1500);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error uploading profile picture', 'error');
    });
});
</script>

</body>
</html>