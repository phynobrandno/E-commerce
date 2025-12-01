<?php
// login_register.php
session_start();

require_once 'config/database.php';
require_once 'classes/User.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

// Handle Login
if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $_SESSION['login_error'] = 'Please fill in all fields!';
        $_SESSION['active_form'] = 'login';
        header('Location: index.php');
        exit();
    }

    $result = $user->login($email, $password);

    if ($result['success']) {
        $_SESSION['user_id'] = $result['user']['id'];
        $_SESSION['username'] = $result['user']['username'];
        $_SESSION['email'] = $result['user']['email'];
        $_SESSION['role'] = $result['user']['role'];
        $_SESSION['logged_in'] = true;

        // Redirect based on role
        if ($result['user']['role'] === 'admin') {
            header('Location: admin/Overview.php');
        } else {
            header('Location: user/dashboard.php');
        }
        exit();
    } else {
        $_SESSION['login_error'] = $result['message'];
        $_SESSION['active_form'] = 'login';
        header('Location: index.php');
        exit();
    }
}

// Handle Registration
if (isset($_POST['register'])) {
    $username = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    if (empty($username) || empty($email) || empty($password) || empty($role)) {
        $_SESSION['register_error'] = 'Please fill in all fields!';
        $_SESSION['active_form'] = 'register';
        header('Location: index.php');
        exit();
    }

    if (strlen($password) < 6) {
        $_SESSION['register_error'] = 'Password must be at least 6 characters!';
        $_SESSION['active_form'] = 'register';
        header('Location: index.php');
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['register_error'] = 'Invalid email format!';
        $_SESSION['active_form'] = 'register';
        header('Location: index.php');
        exit();
    }

    $result = $user->register($username, $email, $password, $role);

    if ($result['success']) {
        $_SESSION['login_error'] = 'Registration successful! Please login.';
        $_SESSION['active_form'] = 'login';
        header('Location: index.php');
        exit();
    } else {
        $_SESSION['register_error'] = $result['message'];
        $_SESSION['active_form'] = 'register';
        header('Location: index.php');
        exit();
    }
}

// If accessed directly, redirect to index
header('Location: index.php');
exit();
?>