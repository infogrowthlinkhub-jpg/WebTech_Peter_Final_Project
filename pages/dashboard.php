<?php
// Protect this page and ensure the user is logged in
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// Get the last completed lesson to find the next lesson
$conn = getDBConnection();
$nextLesson = null;

// Find the last completed lesson
$lastCompletedQuery = "
    SELECT l.id, l.title, l.slug, l.module_id, l.lesson_order, m.slug as module_slug, m.name as module_name
    FROM user_progress up
    INNER JOIN lessons l ON up.lesson_id = l.id
    INNER JOIN modules m ON l.module_id = m.id
    WHERE up.user_id = ? AND up.completed = TRUE
    ORDER BY up.completed_at DESC
    LIMIT 1
";
$stmt = $conn->prepare($lastCompletedQuery);
$stmt->bind_param("i", $currentUserId);
$stmt->execute();
$result = $stmt->get_result();
$lastCompleted = $result->fetch_assoc();
$stmt->close();

if ($lastCompleted) {
    // Find the next lesson in the same module
    $nextLessonQuery = "
        SELECT l.id, l.title, l.slug, l.lesson_order, m.slug as module_slug, m.name as module_name
        FROM lessons l
        INNER JOIN modules m ON l.module_id = m.id
        WHERE l.module_id = ? AND l.lesson_order > ?
        ORDER BY l.lesson_order ASC
        LIMIT 1
    ";
    $stmt = $conn->prepare($nextLessonQuery);
    $stmt->bind_param("ii", $lastCompleted['module_id'], $lastCompleted['lesson_order']);
    $stmt->execute();
    $result = $stmt->get_result();
    $nextLesson = $result->fetch_assoc();
    $stmt->close();
    
    // If no next lesson in same module, check if there are any incomplete lessons in the same module
    if (!$nextLesson) {
        $incompleteQuery = "
            SELECT l.id, l.title, l.slug, l.lesson_order, m.slug as module_slug, m.name as module_name
            FROM lessons l
            INNER JOIN modules m ON l.module_id = m.id
            LEFT JOIN user_progress up ON l.id = up.lesson_id AND up.user_id = ? AND up.completed = TRUE
            WHERE l.module_id = ? AND up.id IS NULL
            ORDER BY l.lesson_order ASC
            LIMIT 1
        ";
        $stmt = $conn->prepare($incompleteQuery);
        $stmt->bind_param("ii", $currentUserId, $lastCompleted['module_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $nextLesson = $result->fetch_assoc();
        $stmt->close();
    }
}

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - NileTech Learning</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .dashboard-header {
            background: linear-gradient(135deg, #004d73 0%, #006994 30%, #008b8b 60%, #4a7c3a 100%);
            color: white;
            padding: 60px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .dashboard-header::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            width: 100%;
            height: 150px;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none"><path d="M0,60 C150,80 350,40 600,60 C850,80 1050,40 1200,60 L1200,120 L0,120 Z" fill="rgba(255,255,255,0.1)"/></svg>') repeat-x;
            background-size: 1200px 150px;
            animation: riverFlow 20s linear infinite;
            opacity: 0.6;
        }
        @keyframes riverFlow {
            0% { background-position-x: 0; }
            100% { background-position-x: 1200px; }
        }
        .dashboard-header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }
        .dashboard-header p {
            position: relative;
            z-index: 1;
        }
        .dashboard-content {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .welcome-message {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            border-top: 4px solid #006994;
        }
        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        .module-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            text-align: center;
            cursor: pointer;
            border-top: 4px solid #006994;
            position: relative;
            overflow: hidden;
        }
        .module-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(0, 107, 148, 0.1), transparent);
            transition: left 0.5s;
        }
        .module-card:hover::before {
            left: 100%;
        }
        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 20px rgba(0, 107, 148, 0.2);
            border-top-color: #008b8b;
        }
        .module-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }
        .module-title {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: #004d73;
        }
        .module-description {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        .btn-start-module {
            background: linear-gradient(135deg, #006994 0%, #008b8b 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .btn-start-module:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(0, 107, 148, 0.4);
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
                <li><a href="profile.php">Profile</a></li>
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
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <h1>Welcome to Your Learning Dashboard</h1>
        <p style="font-size: 1.2rem; opacity: 0.9;">Flow forward with knowledge, like the mighty Nile 🌊</p>
        <p style="font-size: 1rem; opacity: 0.85; margin-top: 10px;">Choose a module to start your learning journey</p>
    </div>

    <!-- Dashboard Content -->
    <div class="dashboard-content">
        <?php if ($nextLesson): ?>
            <div class="welcome-message" style="background: linear-gradient(135deg, #e0f2fe 0%, #dbeafe 100%); border-left: 5px solid #006994;">
                <h2 style="color: #006994; margin-top: 0;">➡️ Continue Where You Left Off</h2>
                <p style="font-size: 1.1rem; margin-bottom: 15px;">
                    <strong>Last completed:</strong> <?php echo htmlspecialchars($lastCompleted['module_name']); ?> - <?php echo htmlspecialchars($lastCompleted['title']); ?>
                </p>
                <p style="font-size: 1.1rem; margin-bottom: 20px;">
                    <strong>Next lesson:</strong> <?php echo htmlspecialchars($nextLesson['module_name']); ?> - <?php echo htmlspecialchars($nextLesson['title']); ?>
                </p>
                <a href="lesson.php?module=<?php echo urlencode($nextLesson['module_slug']); ?>&lesson=<?php echo urlencode($nextLesson['slug']); ?>" 
                   class="btn-start-module" 
                   style="background: linear-gradient(135deg, #006994 0%, #008b8b 100%); font-size: 1.1rem; padding: 15px 40px;">
                    Continue Learning →
                </a>
            </div>
        <?php endif; ?>
        
        <div class="welcome-message">
            <h2>🎓 Ready to Learn?</h2>
            <p>Select a module below to access interactive lessons and start building your skills. Track your progress as you complete each lesson!</p>
        </div>

        <!-- Modules Grid -->
        <div class="modules-grid">
            <!-- Computer Literacy Module -->
            <div class="module-card">
                <div class="module-icon">💻</div>
                <h3 class="module-title">Computer Literacy</h3>
                <p class="module-description">
                    Master the fundamentals of computer usage, from basic operations to file management 
                    and internet navigation. Build confidence in using technology for everyday tasks.
                </p>
                <a href="module.php?module=computer-literacy" class="btn-start-module">Start Learning</a>
            </div>

            <!-- CV Writing Module -->
            <div class="module-card">
                <div class="module-icon">📝</div>
                <h3 class="module-title">CV Writing</h3>
                <p class="module-description">
                    Learn how to create professional CVs and resumes that stand out. Discover the secrets 
                    of effective formatting, content organization, and presentation that employers value.
                </p>
                <a href="module.php?module=cv-writing" class="btn-start-module">Start Learning</a>
            </div>

            <!-- Coding Module -->
            <div class="module-card">
                <div class="module-icon">💻</div>
                <h3 class="module-title">Coding</h3>
                <p class="module-description">
                    Dive into the world of programming and web development. Learn coding fundamentals, 
                    build your first projects, and develop skills that open doors to exciting career opportunities.
                </p>
                <a href="module.php?module=coding" class="btn-start-module">Start Learning</a>
            </div>
        </div>
    </div>

    <!-- Footer -->
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

