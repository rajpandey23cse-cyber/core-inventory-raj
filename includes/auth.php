<?php
session_start();

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        // header('Location: coreinventory/inventory/index.php');
        header('Location: ../index.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if ($_SESSION['user_role'] !== 'admin') {
        header('Location: /inventory/dashboard.php?error=unauthorized');
        exit;
    }
}

function currentUser() {
    return [
        'id'   => $_SESSION['user_id']   ?? null,
        'name' => $_SESSION['user_name'] ?? 'User',
        'email'=> $_SESSION['user_email']?? '',
        'role' => $_SESSION['user_role'] ?? 'staff',
        'avatar'=> $_SESSION['user_avatar']?? null,
    ];
}

function isLoggedIn() {
    // unset($_SESSION['user_id']);   
    return isset($_SESSION['user_id']);
}

function generateRef($prefix) {
    return $prefix . '-' . strtoupper(substr(md5(uniqid()), 0, 8));
}



require_once __DIR__ . '/../includes/auth.php'; // Load session_start() and auth functions

// Check if an action is requested in the URL
if (isset($_GET['action'])) {
    
    // Handle the logout action
    if ($_GET['action'] === 'logout') {
        // 1. Clear all session variables
        $_SESSION = array();

        // 2. Destroy the session cookie if it exists
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        // 3. Completely destroy the session on the server
        session_destroy();

        // 4. Redirect the user back to the login page
        header("Location: ../index.php");
        exit;
    }
}
