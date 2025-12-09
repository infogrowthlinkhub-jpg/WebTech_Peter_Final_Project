<?php
/**
 * API Endpoint: Mentor Contact Submission
 * Handles contact requests to mentors
 */

header('Content-Type: application/json');
session_start();

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'You must be logged in to contact a mentor.']);
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
$mentorName = trim($input['mentor_name'] ?? '');
$subject = trim($input['subject'] ?? '');
$message = trim($input['message'] ?? '');

$errors = [];

// Validation
if (empty($mentorName)) {
    $errors['mentor_name'] = 'Mentor name is required.';
}

if (empty($subject)) {
    $errors['subject'] = 'Subject is required.';
} elseif (strlen($subject) < 3) {
    $errors['subject'] = 'Subject must be at least 3 characters long.';
} elseif (strlen($subject) > 200) {
    $errors['subject'] = 'Subject must not exceed 200 characters.';
}

if (empty($message)) {
    $errors['message'] = 'Message is required.';
} elseif (strlen($message) < 10) {
    $errors['message'] = 'Message must be at least 10 characters long.';
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
    $userId = $_SESSION['user_id'];
    $userName = $_SESSION['user_name'] ?? 'User';
    
    // Get user email from session or database
    $userEmail = $_SESSION['user_email'] ?? '';
    if (empty($userEmail)) {
        // Fetch email from database if not in session
        $emailStmt = $conn->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
        if ($emailStmt) {
            $emailStmt->bind_param('i', $userId);
            $emailStmt->execute();
            $emailResult = $emailStmt->get_result();
            if ($emailRow = $emailResult->fetch_assoc()) {
                $userEmail = $emailRow['email'];
            }
            $emailStmt->close();
        }
    }
    
    // Check if mentor_contacts table exists, if not create it
    $tableCheck = $conn->query("SHOW TABLES LIKE 'mentor_contacts'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        // Create the table if it doesn't exist
        $createTable = "CREATE TABLE IF NOT EXISTS mentor_contacts (
            id INT(11) NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            user_name VARCHAR(100) NOT NULL,
            user_email VARCHAR(100) NOT NULL,
            mentor_name VARCHAR(100) NOT NULL,
            subject VARCHAR(200) NOT NULL,
            message TEXT NOT NULL,
            status ENUM('pending', 'read', 'replied') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_user_id (user_id),
            INDEX idx_mentor_name (mentor_name),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $conn->query($createTable);
    }
    
    // Insert contact request
    $stmt = $conn->prepare(
        'INSERT INTO mentor_contacts (user_id, user_name, user_email, mentor_name, subject, message, status, created_at) 
         VALUES (?, ?, ?, ?, ?, ?, "pending", NOW())'
    );
    
    $stmt->bind_param('isssss', $userId, $userName, $userEmail, $mentorName, $subject, $message);
    
    if ($stmt->execute()) {
        $contactId = $conn->insert_id;
        $stmt->close();
        
        // Get mentor email from database
        $mentorEmail = null;
        $mentorStmt = $conn->prepare('SELECT email FROM mentorship WHERE name = ? LIMIT 1');
        if ($mentorStmt) {
            $mentorStmt->bind_param('s', $mentorName);
            $mentorStmt->execute();
            $mentorResult = $mentorStmt->get_result();
            if ($mentorRow = $mentorResult->fetch_assoc()) {
                $mentorEmail = $mentorRow['email'] ?? null;
            }
            $mentorStmt->close();
        }
        
        // Send email notification to mentor if email exists
        $emailSent = false;
        if (!empty($mentorEmail) && isValidEmail($mentorEmail)) {
            $emailSent = sendMentorContactNotification(
                $mentorEmail,
                $mentorName,
                $userName,
                $userEmail,
                $subject,
                $message
            );
            
            // Log email status (but don't fail the request if email fails)
            if ($emailSent) {
                error_log("Mentor contact notification email sent successfully to: $mentorEmail");
            } else {
                error_log("Mentor contact notification email failed to send to: $mentorEmail (contact request was still saved)");
            }
        } else {
            error_log("Mentor contact notification skipped: No valid email for mentor '$mentorName'");
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Your message has been sent successfully! The mentor will be notified and will respond to you soon.'
        ]);
    } else {
        throw new Exception('Failed to save contact request');
    }
} catch (Exception $e) {
    error_log("Mentor contact API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred while sending your message. Please try again.']);
} finally {
    closeDBConnection($conn);
}
?>

