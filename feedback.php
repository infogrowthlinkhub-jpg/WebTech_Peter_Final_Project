<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$errors = [];
$successMessage = '';
$formData = [
    'feedback_type' => '',
    'title' => '',
    'description' => '',
    'email' => ''
];

// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $feedbackType = trim($_POST['feedback_type'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    // Store form data for repopulation
    $formData = [
        'feedback_type' => $feedbackType,
        'title' => $title,
        'description' => $description,
        'email' => $email
    ];
    
    // Validation
    if (empty($feedbackType) || !in_array($feedbackType, ['experience', 'idea', 'success_story'])) {
        $errors['feedback_type'] = 'Please select a feedback type.';
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
    
    // Email validation (optional field)
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    
    if (empty($errors)) {
        // Get database connection
        $conn = getDBConnection();
        
        // Check if new columns exist, if not use old structure
        $columnsCheck = $conn->query("SHOW COLUMNS FROM feedback LIKE 'feedback_type'");
        $hasNewColumns = $columnsCheck && $columnsCheck->num_rows > 0;
        
        if ($hasNewColumns) {
            // Insert feedback with new fields
            $stmt = $conn->prepare(
                'INSERT INTO feedback (user_id, user_name, feedback_type, title, email, message, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())'
            );
            
            if ($stmt) {
                $userId = $currentUserId;
                $userName = $currentUserName;
                $emailValue = !empty($email) ? $email : null;
                
                $stmt->bind_param('isssss', $userId, $userName, $feedbackType, $title, $emailValue, $description);
            }
        } else {
            // Fallback to old structure
            $stmt = $conn->prepare(
                'INSERT INTO feedback (user_id, user_name, message, created_at) VALUES (?, ?, ?, NOW())'
            );
            
            if ($stmt) {
                $userId = $currentUserId;
                $userName = $currentUserName;
                $message = "[$feedbackType] $title\n\n$description" . (!empty($email) ? "\n\nEmail: $email" : '');
                
                $stmt->bind_param('iss', $userId, $userName, $message);
            }
        }
        
        if ($stmt && $stmt->execute()) {
            $feedbackId = $conn->insert_id;
            
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
            
            $successMessage = 'Thank you for your feedback! Your submission has been received.';
            // Clear form data on success
            $formData = [
                'feedback_type' => '',
                'title' => '',
                'description' => '',
                'email' => ''
            ];
        } else {
            $errors['general'] = 'An error occurred while saving your feedback. Please try again.';
        }
        
        if ($stmt) {
            $stmt->close();
        }
        closeDBConnection($conn);
    }
}

// Fetch all feedback entries (newest first)
$feedbackEntries = [];
$conn = getDBConnection();

// Check if new columns exist
$columnsCheck = $conn->query("SHOW COLUMNS FROM feedback LIKE 'feedback_type'");
$hasNewColumns = $columnsCheck && $columnsCheck->num_rows > 0;

if ($hasNewColumns) {
    $stmt = $conn->prepare('SELECT user_name, feedback_type, title, message, email, created_at FROM feedback ORDER BY created_at DESC LIMIT 50');
} else {
    $stmt = $conn->prepare('SELECT user_name, message, created_at FROM feedback ORDER BY created_at DESC LIMIT 50');
}

if ($stmt) {
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $feedbackEntries[] = $row;
        }
    }
    $stmt->close();
}
closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NileTech - Feedback</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Feedback Modal Styles */
        .feedback-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            animation: fadeIn 0.3s ease;
        }
        
        .feedback-modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            animation: slideUp 0.3s ease;
            position: relative;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .modal-header h2 {
            margin: 0;
            color: #333;
            font-size: 1.75rem;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 2rem;
            cursor: pointer;
            color: #999;
            padding: 0;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s;
        }
        
        .close-modal:hover {
            background: #f0f0f0;
            color: #333;
        }
        
        .feedback-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 0.95rem;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        .error-message {
            color: #dc2626;
            font-size: 0.85rem;
            margin-top: 5px;
            display: none;
        }
        
        .error-message.show {
            display: block;
        }
        
        .form-group input.error,
        .form-group select.error,
        .form-group textarea.error {
            border-color: #dc2626;
        }
        
        .submit-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 14px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
            margin-top: 10px;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .open-modal-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 35px;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
            margin: 20px 0;
        }
        
        .open-modal-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
            display: none;
        }
        
        .success-message.show {
            display: block;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                transform: translateY(-10px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .feedback-page {
            padding: 40px 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .feedback-submission-card {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin: 30px 0;
            border-top: 4px solid #006994;
        }
        
        .feedback-form-main input:focus,
        .feedback-form-main select:focus,
        .feedback-form-main textarea:focus {
            outline: none;
            border-color: #006994;
            box-shadow: 0 0 0 3px rgba(0, 107, 148, 0.1);
        }
        
        .feedback-submission-card .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 107, 148, 0.4);
        }
        
        .feedback-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .feedback-header h1 {
            font-size: 2.5rem;
            margin-bottom: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .feedback-list {
            margin-top: 40px;
        }
        
        .feedback-item {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .feedback-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .feedback-type-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .feedback-type-badge.experience {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .feedback-type-badge.idea {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .feedback-type-badge.success_story {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .feedback-item-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }
        
        .feedback-item-message {
            color: #666;
            line-height: 1.6;
            margin-bottom: 10px;
        }
        
        .feedback-item-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
            color: #999;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }
        
        @media (max-width: 768px) {
            .modal-content {
                padding: 20px;
                margin: 10px;
            }
            
            .feedback-header h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <a href="index.php" style="text-decoration: none; color: inherit;">
                    <h1>NileTech</h1>
                </a>
            </div>
            <button class="menu-toggle" id="menuToggle" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <ul class="nav-menu" id="navMenu">
                <li><a href="index.php">Home</a></li>
                <li><a href="index.php#modules">Modules</a></li>
                <li><a href="mentorship.php">Mentorship</a></li>
                <li><a href="feedback.php" class="active">Feedback</a></li>
                <li class="nav-user">
                    <span>Welcome, <?php echo htmlspecialchars($currentUserName, ENT_QUOTES, 'UTF-8'); ?></span>
                </li>
                <li><a href="logout.php" class="nav-link-login">Logout</a></li>
            </ul>
        </div>
    </nav>

    <!-- Feedback Page -->
    <section class="feedback-page">
        <div class="container">
            <div class="feedback-header">
                <h1>Share Your Feedback</h1>
                <p style="font-size: 1.2rem; color: #666; max-width: 600px; margin: 0 auto;">
                    We value your thoughts and ideas. Let us know how NileTech can better support your learning journey.
                </p>
            </div>

            <!-- Success Message -->
            <?php if ($successMessage !== ''): ?>
                <div class="success-message show" id="successMessage">
                    <strong>✓ Success!</strong> <?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <!-- Prominent Feedback Submission Form -->
            <div class="feedback-submission-card">
                <div style="text-align: center; margin-bottom: 30px;">
                    <h2 style="margin: 0; color: #333; font-size: 2rem; background: var(--gradient-river); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                        Submit Your Feedback
                    </h2>
                    <p style="color: #666; margin-top: 10px; font-size: 1rem;">
                        Your voice matters! Share your thoughts, experiences, or success stories with the NileTech community.
                    </p>
                </div>
                
                <form action="feedback.php" method="POST" id="feedbackFormMain" class="feedback-form-main">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <!-- Feedback Type -->
                        <div class="form-group">
                            <label for="feedback_type_main">Feedback Type <span style="color: #dc2626;">*</span></label>
                            <select id="feedback_type_main" name="feedback_type" required>
                                <option value="">Select a type...</option>
                                <option value="experience" <?php echo $formData['feedback_type'] === 'experience' ? 'selected' : ''; ?>>Experience</option>
                                <option value="idea" <?php echo $formData['feedback_type'] === 'idea' ? 'selected' : ''; ?>>Idea/Suggestion</option>
                                <option value="success_story" <?php echo $formData['feedback_type'] === 'success_story' ? 'selected' : ''; ?>>Success Story</option>
                            </select>
                            <?php if (isset($errors['feedback_type'])): ?>
                                <span class="error-message show"><?php echo htmlspecialchars($errors['feedback_type']); ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Email (Optional) -->
                        <div class="form-group">
                            <label for="email_main">Email (Optional)</label>
                            <input 
                                type="email" 
                                id="email_main" 
                                name="email" 
                                placeholder="your.email@example.com"
                                value="<?php echo htmlspecialchars($formData['email'], ENT_QUOTES, 'UTF-8'); ?>"
                            >
                            <?php if (isset($errors['email'])): ?>
                                <span class="error-message show"><?php echo htmlspecialchars($errors['email']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Title -->
                    <div class="form-group">
                        <label for="title_main">Title <span style="color: #dc2626;">*</span></label>
                        <input 
                            type="text" 
                            id="title_main" 
                            name="title" 
                            placeholder="Enter a brief title for your feedback"
                            value="<?php echo htmlspecialchars($formData['title'], ENT_QUOTES, 'UTF-8'); ?>"
                            required
                            maxlength="200"
                        >
                        <?php if (isset($errors['title'])): ?>
                            <span class="error-message show"><?php echo htmlspecialchars($errors['title']); ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Description -->
                    <div class="form-group">
                        <label for="description_main">Description <span style="color: #dc2626;">*</span></label>
                        <textarea 
                            id="description_main" 
                            name="description" 
                            placeholder="Share your experience, ideas, or success story in detail. What worked well? What could be improved? How has NileTech helped you?"
                            required
                            rows="6"
                        ><?php echo htmlspecialchars($formData['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                        <?php if (isset($errors['description'])): ?>
                            <span class="error-message show"><?php echo htmlspecialchars($errors['description']); ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- General Error -->
                    <?php if (isset($errors['general'])): ?>
                        <div style="background: #fee2e2; color: #dc2626; padding: 12px; border-radius: 8px; border-left: 4px solid #dc2626; margin-bottom: 20px;">
                            <strong>⚠ Error:</strong> <?php echo htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="submit-btn" style="width: 100%; padding: 15px; font-size: 1.1rem;">Submit Feedback</button>
                </form>
            </div>

            <!-- Alternative: Open Modal Button (for users who prefer modal) -->
            <div style="text-align: center; margin: 20px 0;">
                <p style="color: #666; margin-bottom: 10px;">Prefer a popup form?</p>
                <button class="open-modal-btn" id="openFeedbackModal" style="background: var(--gradient-river);">+ Open Feedback Modal</button>
            </div>

            <!-- Feedback Modal -->
            <div class="feedback-modal" id="feedbackModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Submit Your Feedback</h2>
                        <button class="close-modal" id="closeModal">&times;</button>
                    </div>
                    
                    <form action="feedback.php" method="POST" id="feedbackForm" class="feedback-form">
                        <!-- Feedback Type -->
                        <div class="form-group">
                            <label for="feedback_type">Feedback Type <span style="color: #dc2626;">*</span></label>
                            <select id="feedback_type" name="feedback_type" required>
                                <option value="">Select a type...</option>
                                <option value="experience" <?php echo $formData['feedback_type'] === 'experience' ? 'selected' : ''; ?>>Experience</option>
                                <option value="idea" <?php echo $formData['feedback_type'] === 'idea' ? 'selected' : ''; ?>>Idea/Suggestion</option>
                                <option value="success_story" <?php echo $formData['feedback_type'] === 'success_story' ? 'selected' : ''; ?>>Success Story</option>
                            </select>
                            <span class="error-message" id="feedback_type_error">
                                <?php echo isset($errors['feedback_type']) ? htmlspecialchars($errors['feedback_type']) : ''; ?>
                            </span>
                        </div>

                        <!-- Title -->
                        <div class="form-group">
                            <label for="title">Title <span style="color: #dc2626;">*</span></label>
                            <input 
                                type="text" 
                                id="title" 
                                name="title" 
                                placeholder="Enter a brief title for your feedback"
                                value="<?php echo htmlspecialchars($formData['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                required
                                maxlength="200"
                            >
                            <span class="error-message" id="title_error">
                                <?php echo isset($errors['title']) ? htmlspecialchars($errors['title']) : ''; ?>
                            </span>
                        </div>

                        <!-- Description -->
                        <div class="form-group">
                            <label for="description">Description <span style="color: #dc2626;">*</span></label>
                            <textarea 
                                id="description" 
                                name="description" 
                                placeholder="Share your experience, ideas, or success story in detail..."
                                required
                            ><?php echo htmlspecialchars($formData['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                            <span class="error-message" id="description_error">
                                <?php echo isset($errors['description']) ? htmlspecialchars($errors['description']) : ''; ?>
                            </span>
                        </div>

                        <!-- Email (Optional) -->
                        <div class="form-group">
                            <label for="email">Email (Optional)</label>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                placeholder="your.email@example.com"
                                value="<?php echo htmlspecialchars($formData['email'], ENT_QUOTES, 'UTF-8'); ?>"
                            >
                            <span class="error-message" id="email_error">
                                <?php echo isset($errors['email']) ? htmlspecialchars($errors['email']) : ''; ?>
                            </span>
                        </div>

                        <!-- General Error -->
                        <?php if (isset($errors['general'])): ?>
                            <div style="background: #fee2e2; color: #dc2626; padding: 12px; border-radius: 8px; border-left: 4px solid #dc2626;">
                                <?php echo htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>

                        <button type="submit" class="submit-btn" id="submitBtn">Submit Feedback</button>
                    </form>
                </div>
            </div>

            <!-- Feedback List -->
            <div class="feedback-list">
                <h2 style="text-align: center; margin-bottom: 30px; color: #333;">Community Feedback</h2>
                <?php if (count($feedbackEntries) === 0): ?>
                    <p style="text-align: center; color: #999; padding: 40px;">No feedback has been submitted yet. Be the first to share your thoughts!</p>
                <?php else: ?>
                    <div style="display: grid; gap: 20px;">
                        <?php foreach ($feedbackEntries as $entry): ?>
                            <div class="feedback-item">
                                <div class="feedback-item-header">
                                    <?php if (isset($entry['feedback_type'])): ?>
                                        <span class="feedback-type-badge <?php echo htmlspecialchars($entry['feedback_type'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php 
                                            $type = $entry['feedback_type'];
                                            echo ucfirst(str_replace('_', ' ', $type));
                                            ?>
                                        </span>
                                    <?php endif; ?>
                                    <span style="color: #999; font-size: 0.9rem;">
                                        <?php 
                                        $date = $entry['created_at'] ?? '';
                                        echo date('M d, Y', strtotime($date));
                                        ?>
                                    </span>
                                </div>
                                <?php if (isset($entry['title'])): ?>
                                    <div class="feedback-item-title"><?php echo htmlspecialchars($entry['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                                <div class="feedback-item-message">
                                    <?php echo nl2br(htmlspecialchars($entry['message'], ENT_QUOTES, 'UTF-8')); ?>
                                </div>
                                <div class="feedback-item-meta">
                                    <span><strong><?php echo htmlspecialchars($entry['user_name'] ?? 'Anonymous', ENT_QUOTES, 'UTF-8'); ?></strong></span>
                                    <?php if (isset($entry['email']) && !empty($entry['email'])): ?>
                                        <span style="font-size: 0.85rem;"><?php echo htmlspecialchars($entry['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 NileTech Learning Website. Empowering youth through digital education across South Sudan.</p>
            <p style="margin-top: 10px; font-size: 0.9rem; opacity: 0.8;">🌊 Inspired by the Nile River | 🇸🇸 Proudly South Sudanese</p>
        </div>
    </footer>

    <script src="js/script.js"></script>
    <script src="js/feedback.js"></script>
</body>
</html>
