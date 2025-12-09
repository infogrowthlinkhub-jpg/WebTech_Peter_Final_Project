<?php
/**
 * Admin API: Send Notification to User
 * Allows admins to send notifications to users (e.g., feedback replies)
 */

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Check if user is logged in and is admin
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$conn = getDBConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if (!isAdmin($conn, $_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$userId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
$type = isset($input['type']) ? trim($input['type']) : 'admin_message';
$message = isset($input['message']) ? trim($input['message']) : '';

if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Message is required']);
    exit;
}

try {
    $notificationId = createNotification($conn, $userId, $type, $message);
    
    if ($notificationId) {
        echo json_encode([
            'success' => true,
            'message' => 'Notification sent successfully',
            'notification_id' => $notificationId
        ]);
    } else {
        throw new Exception('Failed to create notification');
    }
} catch (Exception $e) {
    error_log("Admin send notification error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
} finally {
    closeDBConnection($conn);
}
?>

