<?php
/**
 * Admin Authentication Check
 * Ensures only admin users can access admin pages
 */

session_start();

if (empty($_SESSION['user_id'])) {
    $_SESSION['error_message'] = 'Please log in to access the admin panel.';
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$conn = getDBConnection();

if (!$conn) {
    $_SESSION['error_message'] = 'Database connection failed.';
    header('Location: ../index.php');
    exit;
}

// Check if user is admin
// First check session variable for quick validation
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    closeDBConnection($conn);
    $_SESSION['error_message'] = 'Access denied. Admin privileges required.';
    header('Location: ../index.php');
    exit;
}

// Then verify with database for security (prevents session hijacking)
$userId = $_SESSION['user_id'];
if (!isAdmin($conn, $userId)) {
    closeDBConnection($conn);
    $_SESSION['error_message'] = 'Access denied. Admin privileges required.';
    header('Location: ../index.php');
    exit;
}

// Get current user info
$userStmt = $conn->prepare("SELECT id, full_name, email, role FROM users WHERE id = ?");
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$userResult = $userStmt->get_result();
$currentUser = $userResult->fetch_assoc();
$userStmt->close();

if (!$currentUser) {
    closeDBConnection($conn);
    session_destroy();
    header('Location: ../login.php');
    exit;
}
?>

