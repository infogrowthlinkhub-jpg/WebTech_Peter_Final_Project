<?php
/**
 * API Endpoint: Mark Notification as Read
 * Marks a notification or all notifications as read
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

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];
$notificationId = isset($input['notification_id']) ? (int)$input['notification_id'] : 0;
$markAll = isset($input['mark_all']) && $input['mark_all'] === true;

$conn = getDBConnection();

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

try {
    if ($markAll) {
        // Mark all notifications as read
        $success = markAllNotificationsAsRead($conn, $userId);
        $message = 'All notifications marked as read';
    } else {
        if ($notificationId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
            exit;
        }
        
        // Mark single notification as read
        $success = markNotificationAsRead($conn, $notificationId, $userId);
        $message = 'Notification marked as read';
    }
    
    if ($success) {
        $unreadCount = getUnreadNotificationsCount($conn, $userId);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'unread_count' => $unreadCount
        ]);
    } else {
        throw new Exception('Failed to mark notification as read');
    }
} catch (Exception $e) {
    error_log("Mark notification read error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
} finally {
    closeDBConnection($conn);
}
?>

