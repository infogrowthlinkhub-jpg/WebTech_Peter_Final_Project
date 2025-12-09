<?php
/**
 * User Profile Page
 * Displays user information and progress statistics
 */
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/certificate.php';

$errors = [];
$successMessage = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullName = trim($_POST['full_name'] ?? '');
    
    // Validation
    if (empty($fullName)) {
        $errors['full_name'] = 'Full name is required.';
    } elseif (strlen($fullName) < 3) {
        $errors['full_name'] = 'Full name must be at least 3 characters long.';
    } elseif (!preg_match("/^[a-zA-Z\s'-]+$/", $fullName)) {
        $errors['full_name'] = 'Full name can only contain letters, spaces, hyphens, and apostrophes.';
    }
    
    if (empty($errors)) {
        $conn = getDBConnection();
        $stmt = $conn->prepare("UPDATE users SET full_name = ? WHERE id = ?");
        $stmt->bind_param("si", $fullName, $currentUserId);
        
        if ($stmt->execute()) {
            $_SESSION['user_name'] = $fullName;
            $successMessage = 'Profile updated successfully!';
        } else {
            $errors['general'] = 'Failed to update profile. Please try again.';
        }
        
        $stmt->close();
        closeDBConnection($conn);
    }
}

// Get user information
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT full_name, email, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $currentUserId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Get user statistics
$statsQuery = "
    SELECT 
        (SELECT COUNT(*) FROM lessons) as total_lessons,
        (SELECT COUNT(*) FROM user_progress WHERE user_id = ? AND completed = TRUE) as completed_lessons,
        (SELECT COUNT(*) FROM feedback WHERE user_id = ?) as feedback_count,
        (SELECT COUNT(*) FROM quiz_scores WHERE user_id = ?) as total_quizzes,
        (SELECT COUNT(*) FROM quiz_scores WHERE user_id = ? AND passed = TRUE) as passed_quizzes,
        (SELECT AVG(percentage) FROM quiz_scores WHERE user_id = ?) as avg_quiz_score
";
$stmt = $conn->prepare($statsQuery);
$stmt->bind_param("iiiii", $currentUserId, $currentUserId, $currentUserId, $currentUserId, $currentUserId);
$stmt->execute();
$result = $stmt->get_result();
$stats = $result->fetch_assoc();
$stmt->close();

// Get module-wise progress
$moduleStatsQuery = "
    SELECT 
        m.id,
        m.name,
        m.slug,
        COUNT(l.id) as total_lessons,
        SUM(CASE WHEN up.completed = TRUE THEN 1 ELSE 0 END) as completed_lessons
    FROM modules m
    LEFT JOIN lessons l ON m.id = l.module_id
    LEFT JOIN user_progress up ON l.id = up.lesson_id AND up.user_id = ?
    GROUP BY m.id, m.name, m.slug
    ORDER BY m.id
";
$stmt = $conn->prepare($moduleStatsQuery);
$stmt->bind_param("i", $currentUserId);
$stmt->execute();
$result = $stmt->get_result();
$moduleStats = [];
while ($row = $result->fetch_assoc()) {
    $moduleStats[] = $row;
}
$stmt->close();

// Get quiz scores with lesson and module information (including all retakes)
$quizScoresQuery = "
    SELECT 
        qs.id,
        qs.score,
        qs.total_questions,
        qs.percentage,
        qs.passed,
        qs.created_at,
        l.title as lesson_title,
        l.slug as lesson_slug,
        l.id as lesson_id,
        m.name as module_name,
        m.slug as module_slug,
        (SELECT COUNT(*) FROM quiz_scores qs2 
         WHERE qs2.user_id = qs.user_id 
         AND qs2.lesson_id = qs.lesson_id 
         AND qs2.created_at <= qs.created_at) as attempt_number
    FROM quiz_scores qs
    INNER JOIN lessons l ON qs.lesson_id = l.id
    INNER JOIN modules m ON l.module_id = m.id
    WHERE qs.user_id = ?
    ORDER BY qs.created_at DESC
";
$stmt = $conn->prepare($quizScoresQuery);
$stmt->bind_param("i", $currentUserId);
$stmt->execute();
$result = $stmt->get_result();
$quizScores = [];
while ($row = $result->fetch_assoc()) {
    $quizScores[] = $row;
}
$stmt->close();

// Get user certificates
$userCertificates = getUserCertificates($conn, $currentUserId);

closeDBConnection($conn);

// Calculate completion percentage
$completionPercentage = $stats['total_lessons'] > 0 
    ? round(($stats['completed_lessons'] / $stats['total_lessons']) * 100, 2)
    : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - NileTech Learning</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .profile-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .profile-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        .profile-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .profile-card h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .stat-item:last-child {
            border-bottom: none;
        }
        .stat-label {
            font-weight: 600;
            color: #666;
        }
        .stat-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: #667eea;
        }
        .progress-bar {
            width: 100%;
            height: 30px;
            background: #e0e0e0;
            border-radius: 15px;
            overflow: hidden;
            margin: 20px 0;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        .module-progress {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .module-progress h4 {
            margin: 0 0 10px 0;
            color: #333;
        }
        .module-progress-bar {
            width: 100%;
            height: 20px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
        }
        .module-progress-fill {
            height: 100%;
            background: #10b981;
            transition: width 0.3s;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 1rem;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        .error-message {
            color: #ef4444;
            font-size: 0.9rem;
            margin-top: 5px;
        }
        .success-message {
            background: #10b981;
            color: white;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        @media (max-width: 768px) {
            .profile-content {
                grid-template-columns: 1fr;
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
                <li><a href="feedback.php">Feedback</a></li>
                <li><a href="profile.php" class="active">Profile</a></li>
                <!-- Notifications Bell -->
                <li style="position: relative;">
                    <a href="#" id="notification-bell" class="nav-link-login" style="position: relative; padding: 8px 12px; text-decoration: none;">
                        🔔
                        <span id="notification-badge" style="display: none; position: absolute; top: 0; right: 0; background: #ef4444; color: white; border-radius: 50%; width: 20px; height: 20px; font-size: 0.7rem; display: flex; align-items: center; justify-content: center; font-weight: bold;">0</span>
                    </a>
                    <div id="notification-dropdown" style="display: none; position: absolute; top: 100%; right: 0; background: white; border: 1px solid #e0e0e0; border-radius: 8px; width: 350px; max-height: 500px; overflow-y: auto; z-index: 1000; box-shadow: 0 4px 12px rgba(0,0,0,0.15); margin-top: 10px;">
                        <div style="padding: 15px; text-align: center; color: #666;">Loading notifications...</div>
                    </div>
                </li>
                <li class="nav-user">
                    <span>Welcome, <?php echo htmlspecialchars($currentUserName, ENT_QUOTES, 'UTF-8'); ?></span>
                </li>
                <li><a href="logout.php" class="nav-link-login">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="profile-container">
        <div class="profile-header">
            <h1>My Profile</h1>
            <p>Manage your account and track your learning progress</p>
        </div>

        <?php if ($successMessage): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($successMessage); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($errors['general'])): ?>
            <div style="background: #fee2e2; color: #dc2626; padding: 1rem; border-radius: 0.5rem; margin-bottom: 20px;">
                <?php echo htmlspecialchars($errors['general']); ?>
            </div>
        <?php endif; ?>

        <div class="profile-content">
            <!-- Profile Information -->
            <div class="profile-card">
                <h3>Profile Information</h3>
                <form method="POST">
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input 
                            type="text" 
                            id="full_name" 
                            name="full_name" 
                            value="<?php echo htmlspecialchars($user['full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            required
                        >
                        <?php if (isset($errors['full_name'])): ?>
                            <div class="error-message"><?php echo htmlspecialchars($errors['full_name']); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            value="<?php echo htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            disabled
                            style="background: #f0f0f0;"
                        >
                        <small style="color: #666;">Email cannot be changed</small>
                    </div>
                    <div class="form-group">
                        <label>Member Since</label>
                        <input 
                            type="text" 
                            value="<?php echo formatDate($user['created_at'] ?? date('Y-m-d')); ?>"
                            disabled
                            style="background: #f0f0f0;"
                        >
                    </div>
                    <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                </form>
            </div>

            <!-- Change Password -->
            <div class="profile-card" style="margin-top: 30px;">
                <h3>Change Password</h3>
                <?php
                $passwordErrors = [];
                $passwordSuccess = '';
                
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
                    $currentPassword = $_POST['current_password'] ?? '';
                    $newPassword = $_POST['new_password'] ?? '';
                    $confirmPassword = $_POST['confirm_password'] ?? '';
                    
                    // Validation
                    if (empty($currentPassword)) {
                        $passwordErrors['current_password'] = 'Current password is required.';
                    }
                    
                    if (empty($newPassword)) {
                        $passwordErrors['new_password'] = 'New password is required.';
                    } elseif (strlen($newPassword) < 8) {
                        $passwordErrors['new_password'] = 'New password must be at least 8 characters long.';
                    }
                    
                    if (empty($confirmPassword)) {
                        $passwordErrors['confirm_password'] = 'Please confirm your new password.';
                    } elseif ($newPassword !== $confirmPassword) {
                        $passwordErrors['confirm_password'] = 'Passwords do not match.';
                    }
                    
                    if (empty($passwordErrors)) {
                        $conn = getDBConnection();
                        
                        // Verify current password
                        $checkStmt = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
                        $checkStmt->bind_param("i", $currentUserId);
                        $checkStmt->execute();
                        $result = $checkStmt->get_result();
                        $user = $result->fetch_assoc();
                        $checkStmt->close();
                        
                        if ($user && password_verify($currentPassword, $user['password'])) {
                            // Update password
                            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                            $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                            $updateStmt->bind_param("si", $hashedPassword, $currentUserId);
                            
                            if ($updateStmt->execute()) {
                                $passwordSuccess = 'Password changed successfully!';
                            } else {
                                $passwordErrors['general'] = 'Failed to change password. Please try again.';
                            }
                            $updateStmt->close();
                        } else {
                            $passwordErrors['current_password'] = 'Current password is incorrect.';
                        }
                        
                        closeDBConnection($conn);
                    }
                }
                ?>
                
                <?php if ($passwordSuccess): ?>
                    <div style="background: #d1fae5; color: #065f46; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; border-left: 4px solid #10b981;">
                        <?php echo htmlspecialchars($passwordSuccess); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($passwordErrors['general'])): ?>
                    <div style="background: #fee2e2; color: #dc2626; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; border-left: 4px solid #dc2626;">
                        <?php echo htmlspecialchars($passwordErrors['general']); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input 
                            type="password" 
                            id="current_password" 
                            name="current_password" 
                            placeholder="Enter your current password"
                            required
                            <?php echo isset($passwordErrors['current_password']) ? 'style="border-color: #ef4444;"' : ''; ?>
                        >
                        <?php if (isset($passwordErrors['current_password'])): ?>
                            <div class="error-message"><?php echo htmlspecialchars($passwordErrors['current_password']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input 
                            type="password" 
                            id="new_password" 
                            name="new_password" 
                            placeholder="Enter new password (min. 8 characters)"
                            required
                            minlength="8"
                            <?php echo isset($passwordErrors['new_password']) ? 'style="border-color: #ef4444;"' : ''; ?>
                        >
                        <?php if (isset($passwordErrors['new_password'])): ?>
                            <div class="error-message"><?php echo htmlspecialchars($passwordErrors['new_password']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            placeholder="Confirm new password"
                            required
                            minlength="8"
                            <?php echo isset($passwordErrors['confirm_password']) ? 'style="border-color: #ef4444;"' : ''; ?>
                        >
                        <?php if (isset($passwordErrors['confirm_password'])): ?>
                            <div class="error-message"><?php echo htmlspecialchars($passwordErrors['confirm_password']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                </form>
            </div>

            <!-- Learning Statistics -->
            <div class="profile-card">
                <h3>Learning Statistics</h3>
                <div class="stat-item">
                    <span class="stat-label">Total Lessons</span>
                    <span class="stat-value"><?php echo $stats['total_lessons']; ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Completed Lessons</span>
                    <span class="stat-value"><?php echo $stats['completed_lessons']; ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Remaining Lessons</span>
                    <span class="stat-value"><?php echo $stats['total_lessons'] - $stats['completed_lessons']; ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Feedback Submitted</span>
                    <span class="stat-value"><?php echo $stats['feedback_count']; ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Quizzes Taken</span>
                    <span class="stat-value"><?php echo $stats['total_quizzes'] ?? 0; ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Quizzes Passed</span>
                    <span class="stat-value"><?php echo $stats['passed_quizzes'] ?? 0; ?></span>
                </div>
                <?php if ($stats['avg_quiz_score']): ?>
                <div class="stat-item">
                    <span class="stat-label">Average Quiz Score</span>
                    <span class="stat-value"><?php echo number_format($stats['avg_quiz_score'], 1); ?>%</span>
                </div>
                <?php endif; ?>
                
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $completionPercentage; ?>%;">
                        <?php echo $completionPercentage; ?>%
                    </div>
                </div>
                <p style="text-align: center; color: #666; margin-top: 10px;">Overall Completion</p>
            </div>
        </div>

        <!-- Module Progress -->
        <div class="profile-card">
            <h3>Module Progress</h3>
            <?php foreach ($moduleStats as $module): 
                $modulePercentage = $module['total_lessons'] > 0 
                    ? round(($module['completed_lessons'] / $module['total_lessons']) * 100, 2)
                    : 0;
            ?>
                <div class="module-progress">
                    <h4>
                        <a href="module.php?module=<?php echo urlencode($module['slug']); ?>" style="text-decoration: none; color: #333;">
                            <?php echo htmlspecialchars($module['name']); ?>
                        </a>
                    </h4>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span><?php echo $module['completed_lessons']; ?> / <?php echo $module['total_lessons']; ?> lessons completed</span>
                        <span style="font-weight: 600; color: #667eea;"><?php echo $modulePercentage; ?>%</span>
                    </div>
                    <div class="module-progress-bar">
                        <div class="module-progress-fill" style="width: <?php echo $modulePercentage; ?>%;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Quiz Scores -->
        <div class="profile-card">
            <h3>Quiz Scores</h3>
            <?php if (empty($quizScores)): ?>
                <div style="padding: 40px; text-align: center; color: #666;">
                    <p style="font-size: 1.1rem; margin-bottom: 10px;">📝 No quiz scores yet</p>
                    <p>Complete lessons and take quizzes to see your scores here.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8f9fa; border-bottom: 2px solid #e0e0e0;">
                                <th style="padding: 12px; text-align: left; font-weight: 600; color: #333;">Lesson</th>
                                <th style="padding: 12px; text-align: left; font-weight: 600; color: #333;">Module</th>
                                <th style="padding: 12px; text-align: center; font-weight: 600; color: #333;">Attempt</th>
                                <th style="padding: 12px; text-align: center; font-weight: 600; color: #333;">Score</th>
                                <th style="padding: 12px; text-align: center; font-weight: 600; color: #333;">Percentage</th>
                                <th style="padding: 12px; text-align: center; font-weight: 600; color: #333;">Status</th>
                                <th style="padding: 12px; text-align: left; font-weight: 600; color: #333;">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($quizScores as $quiz): 
                                $passedClass = $quiz['passed'] ? 'passed' : 'failed';
                                $passedText = $quiz['passed'] ? '✅ Passed' : '❌ Failed';
                                $scoreColor = $quiz['percentage'] >= 80 ? '#10b981' : ($quiz['percentage'] >= 60 ? '#f59e0b' : '#ef4444');
                            ?>
                                <tr style="border-bottom: 1px solid #e0e0e0; transition: background 0.2s;" onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background=''">
                                    <td style="padding: 12px;">
                                        <a href="lesson.php?module=<?php echo urlencode($quiz['module_slug']); ?>&lesson=<?php echo urlencode($quiz['lesson_slug']); ?>" 
                                           style="color: #667eea; text-decoration: none; font-weight: 500;">
                                            <?php echo htmlspecialchars($quiz['lesson_title']); ?>
                                        </a>
                                    </td>
                                    <td style="padding: 12px; color: #666;">
                                        <?php echo htmlspecialchars($quiz['module_name']); ?>
                                    </td>
                                    <td style="padding: 12px; text-align: center;">
                                        <?php 
                                        $attemptNum = isset($quiz['attempt_number']) ? (int)$quiz['attempt_number'] : 1;
                                        if ($attemptNum > 1): 
                                        ?>
                                            <span style="padding: 4px 10px; border-radius: 15px; font-size: 0.85rem; font-weight: 600; 
                                                background: #e0f2fe; color: #0369a1;">
                                                Attempt #<?php echo $attemptNum; ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #666; font-size: 0.9rem;">1st</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px; text-align: center; font-weight: 600; color: <?php echo $scoreColor; ?>;">
                                        <?php echo $quiz['score']; ?> / <?php echo $quiz['total_questions']; ?>
                                    </td>
                                    <td style="padding: 12px; text-align: center; font-weight: 600; color: <?php echo $scoreColor; ?>;">
                                        <?php echo number_format($quiz['percentage'], 1); ?>%
                                    </td>
                                    <td style="padding: 12px; text-align: center;">
                                        <span style="padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; 
                                            background: <?php echo $quiz['passed'] ? '#d1fae5' : '#fee2e2'; ?>; 
                                            color: <?php echo $quiz['passed'] ? '#065f46' : '#991b1b'; ?>;">
                                            <?php echo $passedText; ?>
                                        </span>
                                    </td>
                                    <td style="padding: 12px; color: #666; font-size: 0.9rem;">
                                        <?php echo formatDate($quiz['created_at'], 'M j, Y g:i A'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (count($quizScores) > 0): ?>
                    <p style="text-align: center; color: #666; margin-top: 15px; font-size: 0.9rem;">
                        Showing all <?php echo count($quizScores); ?> quiz attempt<?php echo count($quizScores) !== 1 ? 's' : ''; ?> (including retakes)
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Certificates Section -->
        <div class="profile-card">
            <h3>My Certificates</h3>
            <?php if (empty($userCertificates)): ?>
                <div style="padding: 40px; text-align: center; color: #666;">
                    <p style="font-size: 1.1rem; margin-bottom: 10px;">🏆 No certificates yet</p>
                    <p>Complete a module to earn your first certificate!</p>
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
                    <?php foreach ($userCertificates as $cert): ?>
                        <div style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 20px; border-radius: 10px; border: 2px solid #006994; text-align: center;">
                            <div style="font-size: 3rem; margin-bottom: 10px;">🏆</div>
                            <h4 style="color: #006994; margin: 10px 0;"><?php echo htmlspecialchars($cert['module_name']); ?></h4>
                            <p style="color: #666; font-size: 0.9rem; margin: 5px 0;">
                                Issued: <?php echo formatDate($cert['issued_at']); ?>
                            </p>
                            <a href="certificate.php?module_id=<?php echo $cert['module_id']; ?>" 
                               class="btn btn-primary" 
                               style="margin-top: 15px; display: inline-block;">
                                View Certificate
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 NileTech Learning Website. Empowering youth through digital education across South Sudan.</p>
            <p style="margin-top: 10px; font-size: 0.9rem; opacity: 0.8;">🌊 Inspired by the Nile River | 🇸🇸 Proudly South Sudanese</p>
        </div>
    </footer>

    <script src="js/script.js"></script>
    <script src="js/notifications.js"></script>
</body>
</html>

