<?php
/**
 * NileTech Learning Website - Logout Processing
 * 
 * This file handles user logout:
 * - Destroys session
 * - Clears session data
 * - Redirects to home page
 */

// Start session
session_start();

// Destroy all session data
$_SESSION = array();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to public landing page
header('Location: index.php');
exit();

?>


