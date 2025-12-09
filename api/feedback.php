<?php
/**
 * API Endpoint: Feedback Submission
 * Handles AJAX requests for submitting feedback
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
$feedbackType = trim($input['feedback_type'] ?? '');
$title = trim($input['title'] ?? '');
$description = trim($input['description'] ?? '');
$email = trim($input['email'] ?? '');

$errors = [];

// Validation
if (empty($feedbackType) || !in_array($feedbackType, ['experience', 'idea', 'success_story'])) {
    $errors['feedback_type'] = 'Please select a valid feedback type.';
}

if (empty($title)) {
    $errors['title'] = 'Title is required.';
} elseif (strlen($title) < 3) {
    $errors['title'] = 'Title must be at least 3 characters long.';
} elseif (strlen($title) > 200) {
    $errors['title'] = 'Title must not exceed 200 characters.';
}

if (empty($description)) {
    $errors['description'] = 'Description is required.';
} elseif (strlen($description) < 10) {
    $errors['description'] = 'Description must be at least 10 characters long.';
}

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

$conn = getDBConnection();

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

try {
    // Check if new columns exist
    $columnsCheck = $conn->query("SHOW COLUMNS FROM feedback LIKE 'feedback_type'");
    $hasNewColumns = $columnsCheck && $columnsCheck->num_rows > 0;
    
    $userId = $_SESSION['user_id'];
    $userName = $_SESSION['user_name'] ?? 'User';
    $emailValue = !empty($email) ? $email : null;
    
    if ($hasNewColumns) {
        $stmt = $conn->prepare(
            'INSERT INTO feedback (user_id, user_name, feedback_type, title, email, message, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->bind_param('isssss', $userId, $userName, $feedbackType, $title, $emailValue, $description);
    } else {
        // Fallback to old structure
        $message = "[$feedbackType] $title\n\n$description" . (!empty($email) ? "\n\nEmail: $email" : '');
        $stmt = $conn->prepare(
            'INSERT INTO feedback (user_id, user_name, message, created_at) VALUES (?, ?, ?, NOW())'
        );
        $stmt->bind_param('iss', $userId, $userName, $message);
    }
    
    if ($stmt->execute()) {
        $feedbackId = $conn->insert_id;
        $stmt->close();
        
        // Get user email if not provided in feedback form
        $userEmailForNotification = !empty($emailValue) ? $emailValue : '';
        if (empty($userEmailForNotification)) {
            // Try to get user email from database
            $userEmailStmt = $conn->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
            if ($userEmailStmt) {
                $userEmailStmt->bind_param('i', $userId);
                $userEmailStmt->execute();
                $userEmailResult = $userEmailStmt->get_result();
                if ($userEmailRow = $userEmailResult->fetch_assoc()) {
                    $userEmailForNotification = $userEmailRow['email'] ?? '';
                }
                $userEmailStmt->close();
            }
        }
        
        // Send email notification to africantransformative@gmail.com
        $emailSent = sendFeedbackNotification(
            'africantransformative@gmail.com',
            $userName,
            $userEmailForNotification,
            $feedbackType,
            $title,
            $description
        );
        
        // Log email status (but don't fail the request if email fails)
        if ($emailSent) {
            error_log("Feedback notification email sent successfully to: africantransformative@gmail.com");
        } else {
            error_log("Feedback notification email failed to send (feedback was still saved)");
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Thank you for your feedback! Your submission has been received.'
        ]);
    } else {
        throw new Exception('Failed to save feedback');
    }
} catch (Exception $e) {
    error_log("Feedback API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred while saving your feedback.']);
} finally {
    closeDBConnection($conn);
}
?>

