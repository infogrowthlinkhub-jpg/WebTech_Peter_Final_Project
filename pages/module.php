<?php
/**
 * Module Page - Displays lessons for a selected module
 * 
 * IMPORTANT: This file must be accessed via http://localhost (not file://)
 * URL format: module.php?module=computer-literacy
 */

// Protect this page and ensure the user is logged in
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// Get module slug from URL
$moduleSlug = isset($_GET['module']) ? trim($_GET['module']) : '';

if (empty($moduleSlug)) {
    header('Location: index.php');
    exit;
}

// Get database connection
try {
    $conn = getDBConnection();
    
    // Get module information
    $stmt = $conn->prepare("SELECT id, name, description, icon FROM modules WHERE slug = ?");
    if (!$stmt) {
        throw new Exception("Database query preparation failed");
    }
    
    $stmt->bind_param("s", $moduleSlug);
    $stmt->execute();
    $result = $stmt->get_result();
    $module = $result->fetch_assoc();
    $stmt->close();

    if (!$module) {
        header('Location: index.php');
        exit;
    }

    // Get lessons for this module
    $stmt = $conn->prepare("SELECT id, title, slug, lesson_order, lesson_type FROM lessons WHERE module_id = ? ORDER BY lesson_order ASC");
    if ($stmt) {
        $stmt->bind_param("i", $module['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $lessons = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $lessons = [];
    }

    // Get user progress
    $userId = $_SESSION['user_id'];
    $userProgress = [];
    $progressStmt = $conn->prepare("SELECT lesson_id, completed FROM user_progress WHERE user_id = ?");
    if ($progressStmt) {
        $progressStmt->bind_param("i", $userId);
        $progressStmt->execute();
        $progressResult = $progressStmt->get_result();
        while ($row = $progressResult->fetch_assoc()) {
            $userProgress[$row['lesson_id']] = $row['completed'];
        }
        $progressStmt->close();
    }

    closeDBConnection($conn);
} catch (Exception $e) {
    // Log error and redirect
    error_log("Module page error: " . $e->getMessage());
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($module['name']); ?> - NileTech Learning</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .module-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 20px;
            text-align: center;
        }
        .module-header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        .module-header .module-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }
        .lessons-container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .lessons-list {
            display: grid;
            gap: 20px;
        }
        .lesson-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .lesson-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        .lesson-info {
            flex: 1;
        }
        .lesson-number {
            display: inline-block;
            background: #667eea;
            color: white;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            text-align: center;
            line-height: 35px;
            font-weight: bold;
            margin-right: 15px;
        }
        .lesson-title {
            font-size: 1.3rem;
            margin: 0 0 5px 0;
            color: #333;
        }
        .lesson-type {
            display: inline-block;
            padding: 4px 12px;
            background: #e8f4f8;
            color: #667eea;
            border-radius: 20px;
            font-size: 0.85rem;
            margin-top: 8px;
        }
        .lesson-type.interactive {
            background: #fff3cd;
            color: #856404;
        }
        .lesson-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .completion-badge {
            color: #28a745;
            font-size: 1.2rem;
        }
        .btn-start-lesson {
            background: #667eea;
            color: white;
            padding: 10px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s;
        }
        .btn-start-lesson:hover {
            background: #5568d3;
        }
        .back-link {
            display: inline-block;
            margin: 20px;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        .back-link:hover {
            text-decoration: underline;
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

    <!-- Module Header -->
    <div class="module-header">
        <div class="module-icon"><?php echo htmlspecialchars($module['icon']); ?></div>
        <h1><?php echo htmlspecialchars($module['name']); ?></h1>
        <p style="font-size: 1.1rem; opacity: 0.9;"><?php echo htmlspecialchars($module['description']); ?></p>
    </div>

    <!-- Lessons List -->
    <div class="lessons-container">
        <a href="index.php#modules" class="back-link">← Back to Modules</a>
        
        <h2 style="margin: 30px 0 20px 0; color: #333;">Available Lessons</h2>
        
        <!-- Lesson Search Bar -->
        <div class="search-container" style="max-width: 600px; margin: 20px 0 30px 0;">
            <div class="search-box" style="position: relative;">
                <input type="text" 
                       id="lesson-search-input" 
                       class="search-input" 
                       placeholder="Search lessons by title or content..."
                       style="width: 100%; padding: 12px 45px 12px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1rem; transition: border-color 0.3s;">
                <span style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #666;">🔍</span>
                <div id="lesson-search-results" 
                     class="search-results" 
                     style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #e0e0e0; border-radius: 8px; margin-top: 5px; max-height: 400px; overflow-y: auto; z-index: 1000; box-shadow: 0 4px 12px rgba(0,0,0,0.1);"></div>
            </div>
        </div>
        
        <?php if (empty($lessons)): ?>
            <div style="background: #fff3cd; padding: 20px; border-radius: 10px; margin: 20px 0;">
                <p style="margin: 0; color: #856404;">
                    <strong>No lessons available for this module yet.</strong><br>
                    Lessons will appear here once they are added to the database.
                </p>
            </div>
        <?php else: ?>
            <p style="color: #666; margin-bottom: 20px;">Click on any lesson below to start learning:</p>
            <div class="lessons-list">
                <?php foreach ($lessons as $index => $lesson): ?>
                    <div class="lesson-card" data-lesson-id="<?php echo $lesson['id']; ?>">
                        <div class="lesson-info">
                            <div style="display: flex; align-items: center;">
                                <span class="lesson-number"><?php echo $index + 1; ?></span>
                                <div>
                                    <h3 class="lesson-title"><?php echo htmlspecialchars($lesson['title']); ?></h3>
                                    <span class="lesson-type <?php echo htmlspecialchars($lesson['lesson_type']); ?>">
                                        <?php echo ucfirst(htmlspecialchars($lesson['lesson_type'])); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="lesson-actions">
                            <?php if (isset($userProgress[$lesson['id']]) && $userProgress[$lesson['id']]): ?>
                                <span class="completion-badge" title="Completed">✓</span>
                            <?php endif; ?>
                            <a href="lesson.php?module=<?php echo urlencode($moduleSlug); ?>&lesson=<?php echo urlencode($lesson['slug']); ?>" 
                               class="btn-start-lesson">
                                <?php echo isset($userProgress[$lesson['id']]) && $userProgress[$lesson['id']] ? 'Review' : 'Start Lesson'; ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top: 30px; padding: 15px; background: #e8f4f8; border-radius: 10px;">
                <p style="margin: 0; color: #333;">
                    <strong>Total Lessons:</strong> <?php echo count($lessons); ?> | 
                    <strong>Completed:</strong> <?php echo count(array_filter($userProgress)); ?>
                </p>
                <?php
                // Check if module is complete
                // Calculate progress from data we already have
                $totalLessons = count($lessons);
                $completedLessons = count(array_filter($userProgress));
                $modulePercentage = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100, 2) : 0;
                
                if ($modulePercentage >= 100):
                ?>
                    <div style="margin-top: 15px; padding: 15px; background: #d1fae5; border-radius: 8px; border-left: 4px solid #10b981;">
                        <p style="margin: 0; color: #065f46;">
                            <strong>🏆 Module Complete!</strong> Congratulations on completing this module!
                        </p>
                    </div>
                <?php
                endif;
                ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 NileTech Learning Website. Empowering youth through digital education across South Sudan.</p>
            <p style="margin-top: 10px; font-size: 0.9rem; opacity: 0.8;">🌊 Inspired by the Nile River | 🇸🇸 Proudly South Sudanese</p>
        </div>
    </footer>

    <script src="js/script.js"></script>
    <script src="js/search.js"></script>
    <script src="js/notifications.js"></script>
    <script>
        // Initialize lesson search with module ID
        document.addEventListener('DOMContentLoaded', function() {
            const moduleId = <?php echo $module['id']; ?>;
            initLessonSearch(moduleId);
        });
    </script>
</body>
</html>


