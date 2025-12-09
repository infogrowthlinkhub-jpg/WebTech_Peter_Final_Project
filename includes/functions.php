<?php
/**
 * Utility Functions for NileTech Learning Platform
 * Common functions used across the application
 */

/**
 * Sanitize input data
 * @param string $data Input data to sanitize
 * @return string Sanitized data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validate email address
 * @param string $email Email to validate
 * @return bool True if valid, false otherwise
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Get user progress for a specific module
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @param int $moduleId Module ID
 * @return array Progress data
 */
function getUserModuleProgress($conn, $userId, $moduleId) {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(l.id) as total_lessons,
            SUM(CASE WHEN up.completed = TRUE THEN 1 ELSE 0 END) as completed_lessons
        FROM lessons l
        LEFT JOIN user_progress up ON l.id = up.lesson_id AND up.user_id = ?
        WHERE l.module_id = ?
    ");
    $stmt->bind_param("ii", $userId, $moduleId);
    $stmt->execute();
    $result = $stmt->get_result();
    $progress = $result->fetch_assoc();
    $stmt->close();
    
    $total = (int)$progress['total_lessons'];
    $completed = (int)$progress['completed_lessons'];
    $percentage = $total > 0 ? round(($completed / $total) * 100, 2) : 0;
    
    return [
        'total' => $total,
        'completed' => $completed,
        'remaining' => $total - $completed,
        'percentage' => $percentage
    ];
}

/**
 * Check if lesson is completed by user
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @param int $lessonId Lesson ID
 * @return bool True if completed, false otherwise
 */
function isLessonCompleted($conn, $userId, $lessonId) {
    $stmt = $conn->prepare("SELECT completed FROM user_progress WHERE user_id = ? AND lesson_id = ?");
    $stmt->bind_param("ii", $userId, $lessonId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row && $row['completed'] == 1;
}

/**
 * Format date for display
 * @param string $date Date string
 * @param string $format Date format (default: 'F j, Y')
 * @return string Formatted date
 */
function formatDate($date, $format = 'F j, Y') {
    return date($format, strtotime($date));
}

/**
 * Get time ago string (e.g., "2 hours ago")
 * @param string $datetime Datetime string
 * @return string Time ago string
 */
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return formatDate($datetime);
    }
}

/**
 * Generate CSRF token
 * @return string CSRF token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token Token to verify
 * @return bool True if valid, false otherwise
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Redirect with message
 * @param string $url URL to redirect to
 * @param string $message Message to display
 * @param string $type Message type (success, error, info)
 */
function redirectWithMessage($url, $message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header('Location: ' . $url);
    exit;
}

/**
 * Get and clear flash message
 * @return array|null Flash message array or null
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = [
            'text' => $_SESSION['flash_message'],
            'type' => $_SESSION['flash_type'] ?? 'info'
        ];
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return $message;
    }
    return null;
}

/**
 * Log error to file
 * @param string $message Error message
 * @param array $context Additional context
 */
function logError($message, $context = []) {
    $logFile = __DIR__ . '/../logs/error.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
    $logMessage = "[$timestamp] $message$contextStr" . PHP_EOL;
    
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

/**
 * Send email notification
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $message Email message body (HTML supported)
 * @param string $fromEmail Sender email address (optional)
 * @param string $fromName Sender name (optional)
 * @return bool True if email was sent successfully, false otherwise
 */
function sendEmail($to, $subject, $message, $fromEmail = null, $fromName = null) {
    // Check if email is enabled
    if (file_exists(__DIR__ . '/../config/email.php')) {
        require_once __DIR__ . '/../config/email.php';
        if (function_exists('isEmailEnabled') && !isEmailEnabled()) {
            logError("Email sending is disabled in configuration", ['to' => $to, 'subject' => $subject]);
            return false;
        }
    }
    
    // Validate recipient email
    if (!isValidEmail($to)) {
        logError("Failed to send email: Invalid recipient email address", ['to' => $to]);
        return false;
    }
    
    // Set default from email if not provided
    if (empty($fromEmail)) {
        if (file_exists(__DIR__ . '/../config/email.php')) {
            require_once __DIR__ . '/../config/email.php';
            $fromEmail = function_exists('getDefaultFromEmail') ? getDefaultFromEmail() : 'noreply@niletechlearning.com';
        } else {
            $fromEmail = 'noreply@niletechlearning.com';
        }
    }
    
    // Set default from name if not provided
    if (empty($fromName)) {
        if (file_exists(__DIR__ . '/../config/email.php')) {
            require_once __DIR__ . '/../config/email.php';
            $fromName = function_exists('getDefaultFromName') ? getDefaultFromName() : 'NileTech Learning Platform';
        } else {
            $fromName = 'NileTech Learning Platform';
        }
    }
    
    // Validate sender email
    if (!isValidEmail($fromEmail)) {
        logError("Failed to send email: Invalid sender email address", ['from' => $fromEmail]);
        return false;
    }
    
    // Check if mail function exists
    if (!function_exists('mail')) {
        logError("PHP mail() function is not available. Please configure your mail server.", [
            'to' => $to,
            'subject' => $subject
        ]);
        return false;
    }
    
    // Prepare email headers
    $headers = [];
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-type: text/html; charset=UTF-8";
    $headers[] = "From: $fromName <$fromEmail>";
    $headers[] = "Reply-To: $fromEmail";
    $headers[] = "X-Mailer: PHP/" . phpversion();
    
    $headersString = implode("\r\n", $headers);
    
    // Clear any previous errors
    $lastError = null;
    
    // Send email
    $success = @mail($to, $subject, $message, $headersString);
    
    // Get the last error if mail failed
    if (!$success) {
        $lastError = error_get_last();
        logError("Failed to send email using PHP mail() function", [
            'to' => $to,
            'subject' => $subject,
            'from' => $fromEmail,
            'error' => $lastError,
            'sendmail_path' => ini_get('sendmail_path'),
            'smtp' => ini_get('SMTP'),
            'smtp_port' => ini_get('smtp_port'),
            'note' => 'On XAMPP/local servers, you may need to configure SMTP settings in php.ini or use a mail server'
        ]);
    } else {
        // Log success for debugging
        error_log("Email sent successfully to: $to | Subject: $subject");
    }
    
    return $success;
}

/**
 * Send mentor contact notification email
 * @param string $mentorEmail Mentor's email address
 * @param string $mentorName Mentor's name
 * @param string $userName User's name who sent the message
 * @param string $userEmail User's email address
 * @param string $subject Message subject
 * @param string $message Message content
 * @return bool True if email was sent successfully, false otherwise
 */
function sendMentorContactNotification($mentorEmail, $mentorName, $userName, $userEmail, $subject, $message) {
    // Validate mentor email
    if (empty($mentorEmail) || !isValidEmail($mentorEmail)) {
        logError("Cannot send mentor contact notification: Invalid or missing mentor email", [
            'mentor' => $mentorName,
            'mentor_email' => $mentorEmail
        ]);
        return false;
    }
    
    // Prepare email subject
    $emailSubject = "New Contact Request from NileTech: " . $subject;
    
    // Prepare email body (HTML formatted)
    $emailBody = "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #006994 0%, #00b3b3 100%); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
            .message-box { background: white; padding: 20px; border-left: 4px solid #006994; margin: 20px 0; }
            .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
            .button { display: inline-block; padding: 12px 30px; background: #006994; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🌊 NileTech Learning Platform</h1>
                <p>New Mentor Contact Request</p>
            </div>
            <div class='content'>
                <p>Hello <strong>$mentorName</strong>,</p>
                
                <p>You have received a new contact request through the NileTech Learning Platform mentorship system.</p>
                
                <div class='message-box'>
                    <h3 style='margin-top: 0; color: #006994;'>Message Details:</h3>
                    <p><strong>From:</strong> $userName</p>
                    <p><strong>Email:</strong> <a href='mailto:$userEmail'>$userEmail</a></p>
                    <p><strong>Subject:</strong> $subject</p>
                    <hr style='border: none; border-top: 1px solid #ddd; margin: 15px 0;'>
                    <p><strong>Message:</strong></p>
                    <p style='white-space: pre-wrap;'>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</p>
                </div>
                
                <p>Please respond to this message directly by replying to this email or contacting the user at: <a href='mailto:$userEmail'>$userEmail</a></p>
                
                <p>Best regards,<br>
                <strong>NileTech Learning Platform</strong></p>
            </div>
            <div class='footer'>
                <p>This is an automated notification from NileTech Learning Platform.<br>
                © " . date('Y') . " NileTech Learning Website. Empowering youth through digital education.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Send email
    return sendEmail($mentorEmail, $emailSubject, $emailBody);
}

/**
 * Send feedback notification email
 * @param string $recipientEmail Email address to send feedback to (default: africantransformative@gmail.com)
 * @param string $userName User's name who submitted the feedback
 * @param string $userEmail User's email address (if provided)
 * @param string $feedbackType Type of feedback (experience, idea, success_story)
 * @param string $title Feedback title
 * @param string $message Feedback message content
 * @return bool True if email was sent successfully, false otherwise
 */
function sendFeedbackNotification($recipientEmail, $userName, $userEmail, $feedbackType, $title, $message) {
    // Load email config if available
    if (file_exists(__DIR__ . '/../config/email.php')) {
        require_once __DIR__ . '/../config/email.php';
    }
    
    // Default recipient email
    if (empty($recipientEmail)) {
        $recipientEmail = function_exists('getFeedbackEmail') ? getFeedbackEmail() : 'africantransformative@gmail.com';
    }
    
    // Validate recipient email
    if (!isValidEmail($recipientEmail)) {
        logError("Cannot send feedback notification: Invalid recipient email address", [
            'recipient_email' => $recipientEmail
        ]);
        return false;
    }
    
    // Format feedback type for display
    $feedbackTypeLabels = [
        'experience' => 'Experience',
        'idea' => 'Idea/Suggestion',
        'success_story' => 'Success Story'
    ];
    $feedbackTypeLabel = $feedbackTypeLabels[$feedbackType] ?? ucfirst($feedbackType);
    
    // Prepare email subject
    $emailSubject = "New Feedback from NileTech: " . $title;
    
    // Prepare email body (HTML formatted)
    $userEmailDisplay = !empty($userEmail) ? "<a href='mailto:$userEmail'>$userEmail</a>" : "Not provided";
    
    $emailBody = "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #006994 0%, #00b3b3 100%); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
            .feedback-box { background: white; padding: 20px; border-left: 4px solid #006994; margin: 20px 0; }
            .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; margin-bottom: 10px; }
            .badge-experience { background: #e3f2fd; color: #1976d2; }
            .badge-idea { background: #f3e5f5; color: #7b1fa2; }
            .badge-success_story { background: #e8f5e9; color: #388e3c; }
            .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🌊 NileTech Learning Platform</h1>
                <p>New Feedback Submission</p>
            </div>
            <div class='content'>
                <p>Hello NileTech Team,</p>
                
                <p>You have received a new feedback submission through the NileTech Learning Platform.</p>
                
                <div class='feedback-box'>
                    <span class='badge badge-$feedbackType'>$feedbackTypeLabel</span>
                    <h3 style='margin-top: 10px; margin-bottom: 15px; color: #006994;'>$title</h3>
                    
                    <p><strong>Submitted by:</strong> $userName</p>
                    <p><strong>User Email:</strong> $userEmailDisplay</p>
                    <p><strong>Feedback Type:</strong> $feedbackTypeLabel</p>
                    <p><strong>Date:</strong> " . date('F j, Y \a\t g:i A') . "</p>
                    
                    <hr style='border: none; border-top: 1px solid #ddd; margin: 15px 0;'>
                    <p><strong>Feedback Message:</strong></p>
                    <p style='white-space: pre-wrap; background: #f5f5f5; padding: 15px; border-radius: 5px;'>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</p>
                </div>
                
                <p>You can view all feedback submissions in the NileTech admin panel or database.</p>
                
                <p>Best regards,<br>
                <strong>NileTech Learning Platform</strong></p>
            </div>
            <div class='footer'>
                <p>This is an automated notification from NileTech Learning Platform.<br>
                © " . date('Y') . " NileTech Learning Website. Empowering youth through digital education.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Send email
    return sendEmail($recipientEmail, $emailSubject, $emailBody);
}

/**
 * Check if current user is admin
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @return bool True if user is admin, false otherwise
 */
function isAdmin($conn, $userId) {
    if (empty($userId)) {
        return false;
    }
    
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    return $user && $user['role'] === 'admin';
}

/**
 * Require admin access - redirects if not admin
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @return void Exits if not admin
 */
function requireAdmin($conn, $userId) {
    if (!isAdmin($conn, $userId)) {
        $_SESSION['error_message'] = 'Access denied. Admin privileges required.';
        header('Location: index.php');
        exit;
    }
}

/**
 * Get user role
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @return string User role ('admin' or 'user')
 */
function getUserRole($conn, $userId) {
    if (empty($userId)) {
        return 'user';
    }
    
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return 'user';
    }
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    return $user ? $user['role'] : 'user';
}

/**
 * Create a notification for a user
 * @param mysqli $conn Database connection
 * @param int $userId User ID to notify
 * @param string $type Notification type (e.g., 'lesson_completed', 'feedback_reply', 'admin_message')
 * @param string $message Notification message
 * @return int|false Notification ID on success, false on failure
 */
function createNotification($conn, $userId, $type, $message) {
    if (empty($userId) || empty($type) || empty($message)) {
        logError("Failed to create notification: Missing required parameters", [
            'user_id' => $userId,
            'type' => $type,
            'message_length' => strlen($message ?? '')
        ]);
        return false;
    }
    
    // Check if notifications table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'notifications'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        logError("Notifications table does not exist. Please run the migration SQL file.");
        return false;
    }
    
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
    if (!$stmt) {
        logError("Failed to prepare notification insert statement", ['error' => $conn->error]);
        return false;
    }
    
    $stmt->bind_param("iss", $userId, $type, $message);
    
    if ($stmt->execute()) {
        $notificationId = $conn->insert_id;
        $stmt->close();
        
        // Get user email for email notification
        $userStmt = $conn->prepare("SELECT email, full_name FROM users WHERE id = ? LIMIT 1");
        if ($userStmt) {
            $userStmt->bind_param("i", $userId);
            $userStmt->execute();
            $userResult = $userStmt->get_result();
            if ($user = $userResult->fetch_assoc()) {
                $userEmail = $user['email'] ?? '';
                $userName = $user['full_name'] ?? 'User';
                
                // Send email notification
                if (!empty($userEmail) && isValidEmail($userEmail)) {
                    $emailSubject = "NileTech Notification: " . ucfirst(str_replace('_', ' ', $type));
                    $emailBody = "
                    <!DOCTYPE html>
                    <html lang='en'>
                    <head>
                        <meta charset='UTF-8'>
                        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                            .header { background: linear-gradient(135deg, #006994 0%, #00b3b3 100%); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
                            .notification-box { background: white; padding: 20px; border-left: 4px solid #006994; margin: 20px 0; }
                            .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h1>🌊 NileTech Learning Platform</h1>
                                <p>New Notification</p>
                            </div>
                            <div class='content'>
                                <p>Hello <strong>$userName</strong>,</p>
                                <div class='notification-box'>
                                    <p style='white-space: pre-wrap; margin: 0;'>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</p>
                                </div>
                                <p>You can view all your notifications in your NileTech dashboard.</p>
                                <p>Best regards,<br>
                                <strong>NileTech Learning Platform</strong></p>
                            </div>
                            <div class='footer'>
                                <p>This is an automated notification from NileTech Learning Platform.<br>
                                © " . date('Y') . " NileTech Learning Website. Empowering youth through digital education.</p>
                            </div>
                        </div>
                    </body>
                    </html>
                    ";
                    
                    sendEmail($userEmail, $emailSubject, $emailBody);
                }
            }
            $userStmt->close();
        }
        
        return $notificationId;
    } else {
        logError("Failed to execute notification insert", ['error' => $stmt->error]);
        $stmt->close();
        return false;
    }
}

/**
 * Get unread notifications count for a user
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @return int Number of unread notifications
 */
function getUnreadNotificationsCount($conn, $userId) {
    if (empty($userId)) {
        return 0;
    }
    
    // Check if notifications table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'notifications'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        return 0;
    }
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    if (!$stmt) {
        return 0;
    }
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return (int)($row['count'] ?? 0);
}

/**
 * Get notifications for a user
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @param int $limit Maximum number of notifications to return (default: 20)
 * @param bool $unreadOnly If true, only return unread notifications
 * @return array Array of notifications
 */
function getNotifications($conn, $userId, $limit = 20, $unreadOnly = false) {
    if (empty($userId)) {
        return [];
    }
    
    // Check if notifications table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'notifications'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        return [];
    }
    
    $query = "SELECT id, type, message, is_read, created_at FROM notifications WHERE user_id = ?";
    if ($unreadOnly) {
        $query .= " AND is_read = 0";
    }
    $query .= " ORDER BY created_at DESC LIMIT ?";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param("ii", $userId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'id' => (int)$row['id'],
            'type' => $row['type'],
            'message' => $row['message'],
            'is_read' => (bool)$row['is_read'],
            'created_at' => $row['created_at'],
            'time_ago' => timeAgo($row['created_at'])
        ];
    }
    
    $stmt->close();
    return $notifications;
}

/**
 * Mark notification as read
 * @param mysqli $conn Database connection
 * @param int $notificationId Notification ID
 * @param int $userId User ID (for security, ensure notification belongs to user)
 * @return bool True on success, false on failure
 */
function markNotificationAsRead($conn, $notificationId, $userId) {
    if (empty($notificationId) || empty($userId)) {
        return false;
    }
    
    // Check if notifications table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'notifications'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        return false;
    }
    
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("ii", $notificationId, $userId);
    $success = $stmt->execute();
    $stmt->close();
    
    return $success;
}

/**
 * Mark all notifications as read for a user
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @return bool True on success, false on failure
 */
function markAllNotificationsAsRead($conn, $userId) {
    if (empty($userId)) {
        return false;
    }
    
    // Check if notifications table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'notifications'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        return false;
    }
    
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("i", $userId);
    $success = $stmt->execute();
    $stmt->close();
    
    return $success;
}
?>

