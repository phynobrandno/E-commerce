<?php
session_start();
require_once __DIR__ . '/../classes/UserLayout.php';

// Database connection
$host = 'localhost';
$dbname = 'user_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_pic'])) {
    $targetDir = "../uploads/profiles/";
    
    // Create directory if it doesn't exist
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    $imageFileType = strtolower(pathinfo($_FILES["profile_pic"]["name"], PATHINFO_EXTENSION));
    $newFileName = "user_" . $userId . "_" . time() . "." . $imageFileType;
    $targetFile = $targetDir . $newFileName;
    
    $uploadOk = 1;
    
    // Check if image file is actual image
    $check = getimagesize($_FILES["profile_pic"]["tmp_name"]);
    if ($check === false) {
        $message = "File is not an image.";
        $messageType = "error";
        $uploadOk = 0;
    }
    
    // Check file size (5MB max)
    if ($_FILES["profile_pic"]["size"] > 5000000) {
        $message = "Sorry, your file is too large. Maximum size is 5MB.";
        $messageType = "error";
        $uploadOk = 0;
    }
    
    // Allow certain file formats
    if (!in_array($imageFileType, ["jpg", "jpeg", "png", "gif", "webp"])) {
        $message = "Sorry, only JPG, JPEG, PNG, GIF & WEBP files are allowed.";
        $messageType = "error";
        $uploadOk = 0;
    }
    
    // Check if $uploadOk is set to 0 by an error
    if ($uploadOk == 1) {
        if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $targetFile)) {
            // Get old profile picture to delete it
            $stmt = $pdo->prepare("SELECT profile_pic FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $oldPic = $stmt->fetchColumn();
            
            // Delete old profile picture if exists
            if ($oldPic && file_exists("../" . $oldPic)) {
                unlink("../" . $oldPic);
            }
            
            // Update database
            $relativePath = "uploads/profiles/" . $newFileName;
            $stmt = $pdo->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
            if ($stmt->execute([$relativePath, $userId])) {
                $message = "Profile picture updated successfully!";
                $messageType = "success";
            } else {
                $message = "Database update failed.";
                $messageType = "error";
            }
        } else {
            $message = "Sorry, there was an error uploading your file.";
            $messageType = "error";
        }
    }
}

// Handle profile information update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    // Validate input
    if (empty($username) || empty($email)) {
        $message = "Username and email are required.";
        $messageType = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
        $messageType = "error";
    } else {
        // Check if username or email already exists (excluding current user)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmt->execute([$username, $email, $userId]);
        
        if ($stmt->fetch()) {
            $message = "Username or email already exists.";
            $messageType = "error";
        } else {
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, phone = ?, address = ? WHERE id = ?");
            if ($stmt->execute([$username, $email, $phone, $address, $userId])) {
                $message = "Profile updated successfully!";
                $messageType = "success";
                $_SESSION['username'] = $username;
            } else {
                $message = "Failed to update profile.";
                $messageType = "error";
            }
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Get current password hash
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!password_verify($currentPassword, $user['password'])) {
        $message = "Current password is incorrect.";
        $messageType = "error";
    } elseif (strlen($newPassword) < 6) {
        $message = "New password must be at least 6 characters.";
        $messageType = "error";
    } elseif ($newPassword !== $confirmPassword) {
        $message = "New passwords do not match.";
        $messageType = "error";
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        if ($stmt->execute([$hashedPassword, $userId])) {
            $message = "Password changed successfully!";
            $messageType = "success";
        } else {
            $message = "Failed to change password.";
            $messageType = "error";
        }
    }
}

// Fetch user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
    <link rel="stylesheet" href="../navbar_sidebar.css">
    <style>
        .profile-container {
             margin-left: 100px;
            padding: 30px;
            max-width: 1600px;
            width: 100%;
            box-sizing: border-box;
            margin-top: 35px;
        }
        
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
            margin-top: 35px;
        }
        
        .profile-pic-container {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto 20px;
        }
        
        .profile-pic {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid white;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .profile-pic-overlay {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: white;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            transition: transform 0.2s;
        }
        
        .profile-pic-overlay:hover {
            transform: scale(1.1);
        }
        
        .profile-pic-overlay svg {
            width: 20px;
            height: 20px;
            fill: #667eea;
        }
        
        #profile-pic-input {
            display: none;
        }
        
        .profile-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
        }
        
        .profile-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .profile-card h2 {
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            animation: slideDown 0.3s;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
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
        
        @media (max-width: 768px) {
            .profile-content {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<?php UserLayout::navbar(); ?>
<?php UserLayout::sidebar(); ?>

<div class="profile-container">
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <div class="profile-header">
        <div class="profile-pic-container">
            <?php
            $profilePicPath = '../assets/default-avatar.svg';
            if (!empty($user['profile_pic']) && file_exists('../' . $user['profile_pic'])) {
                $profilePicPath = '../' . htmlspecialchars($user['profile_pic']);
            }
            ?>
            <img src="<?php echo $profilePicPath; ?>" 
                 alt="Profile Picture" 
                 class="profile-pic"
                 id="profile-pic-preview">
            <form method="POST" enctype="multipart/form-data" id="profile-pic-form">
                <label for="profile-pic-input" class="profile-pic-overlay">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                    </svg>
                </label>
                <input type="file" 
                       name="profile_pic" 
                       id="profile-pic-input" 
                       accept="image/*"
                       onchange="this.form.submit()">
            </form>
        </div>
        <h1><?php echo htmlspecialchars($user['username']); ?></h1>
        <p><?php echo htmlspecialchars($user['email']); ?></p>
    </div>
    
    <div class="profile-content">
        <!-- Profile Information Card -->
        <div class="profile-card">
            <h2>Profile Information</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           value="<?php echo htmlspecialchars($user['username']); ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
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
                    <textarea id="address" 
                              name="address"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                </div>
                
                <button type="submit" name="update_profile" class="btn btn-primary">
                    Update Profile
                </button>
            </form>
        </div>
        
        <!-- Change Password Card -->
        <div class="profile-card">
            <h2>Change Password</h2>
            <form method="POST">
                <div class="form-group">
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
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" 
                           id="confirm_password" 
                           name="confirm_password" 
                           required>
                </div>
                
                <button type="submit" name="change_password" class="btn btn-primary">
                    Change Password
                </button>
            </form>
            
            <div style="margin-top: 30px;">
                <h3 style="margin-bottom: 15px; color: #666;">Account Information</h3>
                <div class="info-row">
                    <span class="info-label">Account Type:</span>
                    <span class="info-value"><?php echo ucfirst($user['role']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Member Since:</span>
                    <span class="info-value"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Preview profile picture before upload
    document.getElementById('profile-pic-input').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('profile-pic-preview').src = e.target.result;
            }
            reader.readAsDataURL(file);
        }
    });
</script>

</body>
</html>