<?php 
// index.php
session_start();

// Redirect if already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: user/dashboard.php');
    }
    exit();
}

$errors = [
    'login' => $_SESSION['login_error'] ?? '',
    'register' => $_SESSION['register_error'] ?? ''
];
$activeForm = $_SESSION['active_form'] ?? 'login';

// Clear session variables after use
unset($_SESSION['login_error']);
unset($_SESSION['register_error']);
unset($_SESSION['active_form']);

function showError($error) {
   return !empty($error) ? "<p class='error-message'><i class='fas fa-exclamation-circle'></i> $error</p>" : '';
}

function isActiveForm($formName, $activeForm) {
    return $formName === $activeForm ? 'active' : '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>3D Login & Register</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
            min-height: 100vh;
            position: relative;
        }

        /* Video Background */
        #video-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            background: #000;
        }

        /* 3D Canvas Overlay */
        #canvas-3d {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 2;
        }

        /* Dark Overlay for better readability */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 3;
        }

        /* Form Container */
        .container {
            position: relative;
            z-index: 10;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .form-wrapper {
            position: relative;
            width: 100%;
            max-width: 450px;
        }

        .form-box {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5), 
                        0 0 0 1px rgba(255, 255, 255, 0.1);
            display: none;
            animation: fadeIn 0.5s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .form-box.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-box h2 {
            color: #fff;
            margin-bottom: 30px;
            font-size: 28px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
        }

        .error-message {
            background: rgba(255, 100, 100, 0.2);
            color: #fff;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-left: 4px solid #ff6b6b;
            transition: opacity 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .input-group {
            position: relative;
            margin-bottom: 25px;
        }

        .input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.8);
            font-size: 18px;
            z-index: 2;
        }

        .input-group .toggle-password {
            left: auto;
            right: 15px;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .input-group .toggle-password:hover {
            color: #fff;
        }

        .input-group input,
        .input-group select {
            width: 100%;
            padding: 15px 45px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            backdrop-filter: blur(10px);
        }

        .input-group input::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        .input-group input:focus,
        .input-group select:focus {
            outline: none;
            border-color: rgba(255, 255, 255, 0.5);
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.1);
        }

        .input-group select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 20px;
            color: #fff;
        }

        .input-group select option {
            background: #1a1a1a;
            color: #fff;
        }

        .btn-submit {
            width: 100%;
            padding: 15px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
            backdrop-filter: blur(10px);
        }

        .btn-submit:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.5);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .text {
            text-align: center;
            margin-top: 20px;
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
        }

        .text a {
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            text-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
        }

        .text a:hover {
            text-decoration: underline;
        }

        /* Responsive Design */
        @media (max-width: 500px) {
            .form-box {
                padding: 30px 20px;
            }
            
            .form-box h2 {
                font-size: 24px;
            }
            
            .input-group input,
            .input-group select {
                padding: 12px 40px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <!-- Video Background -->
    <div id="video-background"></div>
    
    <!-- 3D Canvas -->
    <canvas id="canvas-3d"></canvas>
    
    <!-- Dark Overlay -->
    <div class="overlay"></div>

    <!-- Form Container -->
    <div class="container">
        <div class="form-wrapper">
            <!-- Login Form -->
            <div class="form-box <?= isActiveForm('login', $activeForm); ?>" id="login-form">
                <form action="login_register.php" method="post">
                    <h2><i class="fas fa-sign-in-alt"></i> Login</h2>
                    <?= showError($errors['login']) ?>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" placeholder="Email" name="email" required>
                    </div>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" placeholder="Password" name="password" id="login-password" required>
                        <i class="fas fa-eye toggle-password" onclick="togglePassword('login-password', this)"></i>
                    </div>
                    <button type="submit" name="login" class="btn-submit">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                    <p class="text">Don't have an account? <a href="#" onclick="showForm('register-form')">Register</a></p>
                </form>
            </div>

            <!-- Register Form -->
            <div class="form-box <?= isActiveForm('register', $activeForm); ?>" id="register-form">
                <form action="login_register.php" method="post">
                    <h2><i class="fas fa-user-plus"></i> Register</h2>
                    <?= showError($errors['register']) ?>
                    <div class="input-group">
                        <i class="fas fa-user"></i>
                        <input type="text" placeholder="Username" name="name" required>
                    </div>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" placeholder="Email" name="email" required>
                    </div>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" placeholder="Password" name="password" id="register-password" required>
                        <i class="fas fa-eye toggle-password" onclick="togglePassword('register-password', this)"></i>
                    </div>
                    <div class="input-group">
                        <i class="fas fa-user-tag"></i>
                        <select name="role" required>
                            <option value="">Select role</option>
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <button type="submit" name="register" class="btn-submit">
                        <i class="fas fa-user-plus"></i> Register
                    </button>
                    <p class="text">Already have an account? <a href="#" onclick="showForm('login-form')">Login</a></p>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="script.js"></script>
</body>
</html>