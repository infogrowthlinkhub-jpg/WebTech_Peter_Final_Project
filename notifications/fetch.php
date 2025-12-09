<?php
/**
 * API Endpoint: Fetch Notifications
 * Returns JSON list of unread notifications for the current user
 */

header('Content-Type: application/json');
session_start();

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$userId = $_SESSION['user_id'];
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';

$conn = getDBConnection();

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

try {
    $notifications = getNotifications($conn, $userId, $limit, $unreadOnly);
    $unreadCount = getUnreadNotificationsCount($conn, $userId);
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unreadCount
    ]);
} catch (Exception $e) {
    error_log("Fetch notifications error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
} finally {
    closeDBConnection($conn);
}
?>

