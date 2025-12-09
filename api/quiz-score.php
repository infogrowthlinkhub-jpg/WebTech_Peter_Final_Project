<?php
/**
 * API Endpoint: Quiz Score Submission
 * Handles AJAX requests for saving quiz scores and tracking quiz completion
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

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$lessonId = isset($input['lesson_id']) ? (int)$input['lesson_id'] : 0;
$score = isset($input['score']) ? (int)$input['score'] : 0;
$totalQuestions = isset($input['total_questions']) ? (int)$input['total_questions'] : 0;
$percentage = isset($input['percentage']) ? (float)$input['percentage'] : 0;

// Validate input
if ($lessonId <= 0 || $totalQuestions <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid quiz data']);
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
    // Minimum passing score: 60%
    $passingThreshold = 60.0;
    $passed = ($percentage >= $passingThreshold);
    
    // Insert quiz score (allow multiple attempts - we'll check the latest/best score)
    $stmt = $conn->prepare("
        INSERT INTO quiz_scores (user_id, lesson_id, score, total_questions, percentage, passed)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    if (!$stmt) {
        throw new Exception('Failed to prepare quiz score query');
    }
    
    $stmt->bind_param("iiiddi", $userId, $lessonId, $score, $totalQuestions, $percentage, $passed);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to save quiz score');
    }
    
    $stmt->close();
    
    // Also mark lesson as completed if quiz was passed
    if ($passed) {
        // Check if progress record exists
        $checkStmt = $conn->prepare("SELECT id FROM user_progress WHERE user_id = ? AND lesson_id = ?");
        if ($checkStmt) {
            $checkStmt->bind_param("ii", $userId, $lessonId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                // Update existing progress
                $updateStmt = $conn->prepare("UPDATE user_progress SET completed = TRUE, completed_at = NOW() WHERE user_id = ? AND lesson_id = ?");
                if ($updateStmt) {
                    $updateStmt->bind_param("ii", $userId, $lessonId);
                    $updateStmt->execute();
                    $updateStmt->close();
                }
            } else {
                // Insert new progress
                $insertStmt = $conn->prepare("INSERT INTO user_progress (user_id, lesson_id, completed, completed_at) VALUES (?, ?, TRUE, NOW())");
                if ($insertStmt) {
                    $insertStmt->bind_param("ii", $userId, $lessonId);
                    $insertStmt->execute();
                    $insertStmt->close();
                }
            }
            $checkStmt->close();
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Quiz score saved successfully',
        'passed' => $passed,
        'percentage' => $percentage,
        'score' => $score,
        'total_questions' => $totalQuestions,
        'passing_threshold' => $passingThreshold
    ]);
    
} catch (Exception $e) {
    error_log("Quiz score error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save quiz score: ' . $e->getMessage()]);
} finally {
    if (isset($conn)) {
        closeDBConnection($conn);
    }
}

