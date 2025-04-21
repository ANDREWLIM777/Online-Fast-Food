<?php
session_start();

// Clear all session data
$_SESSION = [];

// Destroy the session cookie if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Fully destroy the session
session_destroy();

// Redirect to register page or login
header("Location: ../customer/login.php?logout=1");
exit();
