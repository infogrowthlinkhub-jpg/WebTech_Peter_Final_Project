<?php
/**
 * API Endpoint: Progress Tracking
 * Handles AJAX requests for marking lessons as complete
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
$lessonId = isset($input['lesson_id']) ? (int)$input['lesson_id'] : 0;
$action = isset($input['action']) ? $input['action'] : 'complete';

if ($lessonId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid lesson ID']);
    exit;
}

$userId = $_SESSION['user_id'];
$conn = getDBConnection();

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

try {
    if ($action === 'complete') {
        // Check if progress already exists
        $checkStmt = $conn->prepare("SELECT id FROM user_progress WHERE user_id = ? AND lesson_id = ?");
        $checkStmt->bind_param("ii", $userId, $lessonId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing progress
            $updateStmt = $conn->prepare("UPDATE user_progress SET completed = TRUE, completed_at = NOW() WHERE user_id = ? AND lesson_id = ?");
            $updateStmt->bind_param("ii", $userId, $lessonId);
            $success = $updateStmt->execute();
            $updateStmt->close();
        } else {
            // Insert new progress
            $insertStmt = $conn->prepare("INSERT INTO user_progress (user_id, lesson_id, completed, completed_at) VALUES (?, ?, TRUE, NOW())");
            $insertStmt->bind_param("ii", $userId, $lessonId);
            $success = $insertStmt->execute();
            $insertStmt->close();
        }
        $checkStmt->close();
        
        if ($success) {
            // Get lesson information for notification
            $lessonStmt = $conn->prepare("SELECT title, module_id FROM lessons WHERE id = ?");
            $lessonStmt->bind_param("i", $lessonId);
            $lessonStmt->execute();
            $lessonResult = $lessonStmt->get_result();
            $lesson = $lessonResult->fetch_assoc();
            $lessonStmt->close();
            
            // Get module name
            $moduleName = 'Module';
            if ($lesson && isset($lesson['module_id'])) {
                $moduleStmt = $conn->prepare("SELECT name FROM modules WHERE id = ?");
                $moduleStmt->bind_param("i", $lesson['module_id']);
                $moduleStmt->execute();
                $moduleResult = $moduleStmt->get_result();
                $module = $moduleResult->fetch_assoc();
                if ($module) {
                    $moduleName = $module['name'];
                }
                $moduleStmt->close();
            }
            
            // Create notification for lesson completion
            $lessonTitle = $lesson ? $lesson['title'] : 'Lesson';
            $notificationMessage = "Congratulations! You completed the lesson: {$lessonTitle} in {$moduleName}.";
            createNotification($conn, $userId, 'lesson_completed', $notificationMessage);
            
            // Get completion statistics
            $statsStmt = $conn->prepare("
                SELECT 
                    COUNT(*) as total_lessons,
                    SUM(CASE WHEN up.completed = TRUE THEN 1 ELSE 0 END) as completed_lessons
                FROM lessons l
                LEFT JOIN user_progress up ON l.id = up.lesson_id AND up.user_id = ?
                WHERE l.module_id = (SELECT module_id FROM lessons WHERE id = ?)
            ");
            $statsStmt->bind_param("ii", $userId, $lessonId);
            $statsStmt->execute();
            $statsResult = $statsStmt->get_result();
            $stats = $statsResult->fetch_assoc();
            $statsStmt->close();
            
            echo json_encode([
                'success' => true,
                'message' => 'Lesson marked as complete',
                'stats' => $stats
            ]);
        } else {
            throw new Exception('Failed to update progress');
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Progress API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
} finally {
    closeDBConnection($conn);
}
?>

