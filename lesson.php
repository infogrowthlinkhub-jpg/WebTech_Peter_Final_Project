<?php
// Protect this page and ensure the user is logged in
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// Get module and lesson slugs from URL
$moduleSlug = isset($_GET['module']) ? trim($_GET['module']) : '';
$lessonSlug = isset($_GET['lesson']) ? trim($_GET['lesson']) : '';

// Sanitize input
$moduleSlug = htmlspecialchars($moduleSlug, ENT_QUOTES, 'UTF-8');
$lessonSlug = htmlspecialchars($lessonSlug, ENT_QUOTES, 'UTF-8');

if (empty($moduleSlug) || empty($lessonSlug)) {
    header('Location: index.php');
    exit;
}

// Get database connection with error handling
try {
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Get module information
    $stmt = $conn->prepare("SELECT id, name, slug FROM modules WHERE slug = ?");
    if (!$stmt) {
        throw new Exception('Failed to prepare module query');
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

    // Get lesson information including lesson_order for prerequisite checking
    $stmt = $conn->prepare("SELECT id, title, content, lesson_type, code_example, expected_output, lesson_order FROM lessons WHERE slug = ? AND module_id = ?");
    if (!$stmt) {
        throw new Exception('Failed to prepare lesson query');
    }
    $stmt->bind_param("si", $lessonSlug, $module['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $lesson = $result->fetch_assoc();
    $stmt->close();

    if (!$lesson) {
        header('Location: module.php?module=' . urlencode($moduleSlug));
        exit;
    }
} catch (Exception $e) {
    error_log("Lesson page error: " . $e->getMessage());
    header('Location: index.php');
    exit;
}

// Mark lesson as completed if requested (before closing connection)
$completionMessage = '';
if (isset($_POST['mark_complete'])) {
    try {
        $userId = $_SESSION['user_id'];
        $lessonId = $lesson['id'];
        
        $checkStmt = $conn->prepare("SELECT id FROM user_progress WHERE user_id = ? AND lesson_id = ?");
        if ($checkStmt) {
            $checkStmt->bind_param("ii", $userId, $lessonId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $updateStmt = $conn->prepare("UPDATE user_progress SET completed = TRUE, completed_at = NOW() WHERE user_id = ? AND lesson_id = ?");
                if ($updateStmt) {
                    $updateStmt->bind_param("ii", $userId, $lessonId);
                    if ($updateStmt->execute()) {
                        $completionMessage = 'success';
                    }
                    $updateStmt->close();
                }
            } else {
                $insertStmt = $conn->prepare("INSERT INTO user_progress (user_id, lesson_id, completed, completed_at) VALUES (?, ?, TRUE, NOW())");
                if ($insertStmt) {
                    $insertStmt->bind_param("ii", $userId, $lessonId);
                    if ($insertStmt->execute()) {
                        $completionMessage = 'success';
                    }
                    $insertStmt->close();
                }
            }
            $checkStmt->close();
        }
    } catch (Exception $e) {
        error_log("Progress update error: " . $e->getMessage());
        $completionMessage = 'error';
    }
}

// Get previous and next lessons with their IDs
$prevLesson = null;
$nextLesson = null;
$canAccessCurrentLesson = true;
$canAccessNextLesson = false;

try {
    $userId = $_SESSION['user_id'];
    
    // Get previous lesson with ID
    $prevStmt = $conn->prepare("SELECT id, slug, lesson_order FROM lessons WHERE module_id = ? AND lesson_order < (SELECT lesson_order FROM lessons WHERE slug = ? AND module_id = ?) ORDER BY lesson_order DESC LIMIT 1");
    if ($prevStmt) {
        $prevStmt->bind_param("isi", $module['id'], $lessonSlug, $module['id']);
        $prevStmt->execute();
        $prevResult = $prevStmt->get_result();
        $prevLesson = $prevResult->fetch_assoc();
        $prevStmt->close();
        
        // Check if previous lesson quiz was passed (prerequisite check)
        // Only check if this is not the first lesson (lesson_order > 1)
        // Skip quiz prerequisites for CV Writing module (slug: 'cv-writing')
        if ($prevLesson && $lesson['lesson_order'] > 1 && $moduleSlug !== 'cv-writing') {
            $prevQuizCheckStmt = $conn->prepare("SELECT passed FROM quiz_scores WHERE user_id = ? AND lesson_id = ? AND passed = TRUE ORDER BY created_at DESC LIMIT 1");
            if ($prevQuizCheckStmt) {
                $prevQuizCheckStmt->bind_param("ii", $userId, $prevLesson['id']);
                $prevQuizCheckStmt->execute();
                $prevQuizResult = $prevQuizCheckStmt->get_result();
                
                // If previous lesson exists and quiz was not passed, block access
                if ($prevQuizResult->num_rows === 0) {
                    $canAccessCurrentLesson = false;
                }
                
                $prevQuizCheckStmt->close();
            }
        }
    }

    // Get next lesson with ID
    $nextStmt = $conn->prepare("SELECT id, slug, lesson_order FROM lessons WHERE module_id = ? AND lesson_order > (SELECT lesson_order FROM lessons WHERE slug = ? AND module_id = ?) ORDER BY lesson_order ASC LIMIT 1");
    if ($nextStmt) {
        $nextStmt->bind_param("isi", $module['id'], $lessonSlug, $module['id']);
        $nextStmt->execute();
        $nextResult = $nextStmt->get_result();
        $nextLesson = $nextResult->fetch_assoc();
        $nextStmt->close();
        
        // Check if current lesson quiz was passed (to allow next lesson access)
        // Skip quiz prerequisites for CV Writing module (slug: 'cv-writing')
        if ($nextLesson) {
            if ($moduleSlug === 'cv-writing') {
                // Always allow access to next lesson in CV Writing module (no quiz required)
                $canAccessNextLesson = true;
            } else {
                // For other modules, check if quiz was passed
                $currentQuizCheckStmt = $conn->prepare("SELECT passed FROM quiz_scores WHERE user_id = ? AND lesson_id = ? AND passed = TRUE ORDER BY created_at DESC LIMIT 1");
                if ($currentQuizCheckStmt) {
                    $currentQuizCheckStmt->bind_param("ii", $userId, $lesson['id']);
                    $currentQuizCheckStmt->execute();
                    $currentQuizResult = $currentQuizCheckStmt->get_result();
                    
                    if ($currentQuizResult->num_rows > 0) {
                        $canAccessNextLesson = true;
                    }
                    
                    $currentQuizCheckStmt->close();
                }
            }
        }
    }
} catch (Exception $e) {
    error_log("Lesson navigation error: " . $e->getMessage());
}

// Block access to current lesson if prerequisite not met (before closing connection)
// Skip blocking for CV Writing module (slug: 'cv-writing')
if (!$canAccessCurrentLesson && $prevLesson && $moduleSlug !== 'cv-writing') {
    // Get previous lesson details for error message
    $prevLessonStmt = $conn->prepare("SELECT title FROM lessons WHERE id = ?");
    if ($prevLessonStmt) {
        $prevLessonStmt->bind_param("i", $prevLesson['id']);
        $prevLessonStmt->execute();
        $prevLessonResult = $prevLessonStmt->get_result();
        $prevLessonData = $prevLessonResult->fetch_assoc();
        $prevLessonStmt->close();
        
        // Close connection before redirect
        if (isset($conn)) {
            closeDBConnection($conn);
        }
        
        $_SESSION['error_message'] = "You must pass the quiz for '" . htmlspecialchars($prevLessonData['title'], ENT_QUOTES, 'UTF-8') . "' before accessing this lesson. Please complete the quiz with at least 60% to proceed.";
        header('Location: lesson.php?module=' . urlencode($moduleSlug) . '&lesson=' . urlencode($prevLesson['slug']));
        exit;
    }
}

// Close database connection
if (isset($conn)) {
    closeDBConnection($conn);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($lesson['title']); ?> - NileTech Learning</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/monokai.min.css">
    <style>
        .lesson-page {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        .lesson-header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .lesson-header h1 {
            margin: 0 0 10px 0;
            color: #333;
        }
        .breadcrumb {
            color: #666;
            margin-bottom: 15px;
        }
        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
        }
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        .lesson-content-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        @media (max-width: 1024px) {
            .lesson-content-wrapper {
                grid-template-columns: 1fr;
            }
        }
        .lesson-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .lesson-content h2, .lesson-content h3 {
            color: #333;
            margin-top: 25px;
        }
        .lesson-content ul, .lesson-content ol {
            margin: 15px 0;
            padding-left: 30px;
        }
        .lesson-content li {
            margin: 8px 0;
        }
        .code-editor-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .code-editor-section h3 {
            margin-top: 0;
            color: #333;
        }
        .editor-container {
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            margin: 15px 0;
            overflow: hidden;
        }
        .CodeMirror {
            height: 400px;
            font-size: 14px;
        }
        .editor-actions {
            display: flex;
            gap: 10px;
            margin: 15px 0;
        }
        .btn-run {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
        }
        .btn-run:hover {
            background: #218838;
        }
        .btn-reset {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
        }
        .btn-reset:hover {
            background: #5a6268;
        }
        .output-container {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 5px;
            border: 2px solid #e0e0e0;
        }
        .output-container h4 {
            margin-top: 0;
            color: #333;
        }
        .output-frame {
            width: 100%;
            height: 500px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: white;
        }
        .lesson-navigation {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
        }
        .nav-btn {
            background: #667eea;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .nav-btn:hover {
            background: #5568d3;
        }
        .nav-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .complete-section {
            background: #e8f5e9;
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
            text-align: center;
        }
        .btn-complete {
            background: #28a745;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            margin-top: 10px;
        }
        .btn-complete:hover {
            background: #218838;
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
                <li class="nav-user">
                    <span>Welcome, <?php echo htmlspecialchars($currentUserName, ENT_QUOTES, 'UTF-8'); ?></span>
                </li>
                <li><a href="logout.php" class="nav-link-login">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="lesson-page">
        <!-- Lesson Header -->
        <div class="lesson-header">
            <div class="breadcrumb">
                <a href="index.php">Home</a> / 
                <a href="module.php?module=<?php echo urlencode($moduleSlug); ?>"><?php echo htmlspecialchars($module['name']); ?></a> / 
                <?php echo htmlspecialchars($lesson['title']); ?>
            </div>
            <h1><?php echo htmlspecialchars($lesson['title']); ?></h1>
        </div>

        <!-- Lesson Content -->
        <div class="lesson-content-wrapper">
            <div class="lesson-content" data-lesson-id="<?php echo $lesson['id']; ?>">
                <?php echo $lesson['content']; ?>
            </div>

            <?php if ($lesson['lesson_type'] === 'interactive' && !empty($lesson['code_example'])): ?>
            <div class="code-editor-section">
                <h3>Interactive Code Editor</h3>
                <p>Edit the code below and click "Run Code" to see the result!</p>
                
                <div class="editor-container">
                    <textarea id="codeEditor"><?php echo htmlspecialchars($lesson['code_example']); ?></textarea>
                </div>
                
                <div class="editor-actions">
                    <button class="btn-run" onclick="runCode()">Run Code</button>
                    <button class="btn-reset" onclick="resetCode()">Reset</button>
                </div>

                <?php if (!empty($lesson['expected_output'])): ?>
                <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 15px 0;">
                    <strong>Expected Output:</strong> <?php echo htmlspecialchars($lesson['expected_output']); ?>
                </div>
                <?php endif; ?>

                <div class="output-container">
                    <h4>Output:</h4>
                    <iframe id="outputFrame" class="output-frame" sandbox="allow-scripts allow-same-origin"></iframe>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Complete Lesson Section -->
        <div class="complete-section">
            <?php if ($completionMessage === 'success'): ?>
                <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                    <strong>✓ Lesson marked as complete!</strong> Great job on finishing this lesson.
                </div>
            <?php endif; ?>
            <h3>Completed this lesson?</h3>
            <p>Mark it as complete to track your progress!</p>
            <form method="POST" style="display: inline;">
                <button type="submit" name="mark_complete" class="btn-complete" data-lesson-id="<?php echo $lesson['id']; ?>">Mark as Complete ✓</button>
            </form>
        </div>

        <!-- Navigation -->
        <div class="lesson-navigation">
            <?php if ($prevLesson): ?>
                <a href="lesson.php?module=<?php echo urlencode($moduleSlug); ?>&lesson=<?php echo urlencode($prevLesson['slug']); ?>" class="nav-btn">← Previous Lesson</a>
            <?php else: ?>
                <span class="nav-btn" style="background: #ccc; cursor: not-allowed;">← Previous Lesson</span>
            <?php endif; ?>

            <a href="module.php?module=<?php echo urlencode($moduleSlug); ?>" class="nav-btn">Back to Module</a>

            <?php if ($nextLesson): ?>
                <?php if ($canAccessNextLesson): ?>
                    <a href="lesson.php?module=<?php echo urlencode($moduleSlug); ?>&lesson=<?php echo urlencode($nextLesson['slug']); ?>" class="nav-btn">Next Lesson →</a>
                <?php else: ?>
                    <span class="nav-btn" style="background: #f59e0b; cursor: not-allowed;" title="You must pass the quiz (60% or higher) to unlock the next lesson">🔒 Next Lesson → (Pass Quiz to Unlock)</span>
                <?php endif; ?>
            <?php else: ?>
                <span class="nav-btn" style="background: #ccc; cursor: not-allowed;">Next Lesson →</span>
            <?php endif; ?>
        </div>
        
        <?php if (!$canAccessNextLesson && $nextLesson && $moduleSlug !== 'cv-writing'): ?>
            <div style="background: #fff3cd; border-left: 4px solid #f59e0b; padding: 15px; border-radius: 5px; margin-top: 20px;">
                <strong>⚠️ Quiz Required:</strong> You must pass this lesson's quiz with at least <strong>60%</strong> to unlock the next lesson. Complete the quiz below to proceed!
            </div>
        <?php endif; ?>
        
        <?php 
        // Clear any error messages from session to avoid duplicate warnings
        if (isset($_SESSION['error_message'])) {
            unset($_SESSION['error_message']);
        }
        ?>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 NileTech Learning Website. Empowering youth through digital education across South Sudan.</p>
            <p style="margin-top: 10px; font-size: 0.9rem; opacity: 0.8;">🌊 Inspired by the Nile River | 🇸🇸 Proudly South Sudanese</p>
        </div>
    </footer>

    <!-- CodeMirror Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/css/css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/htmlmixed/htmlmixed.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/clike/clike.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/php/php.min.js"></script>

    <script src="js/script.js"></script>
    <script>
        // Store lesson ID globally for quiz submission
        const CURRENT_LESSON_ID = <?php echo $lesson['id']; ?>;
        
        // Initialize CodeMirror
        let editor;
        let originalCode = '';
        const codeTextarea = document.getElementById('codeEditor');
        
        if (codeTextarea) {
            editor = CodeMirror.fromTextArea(codeTextarea, {
                lineNumbers: true,
                mode: 'htmlmixed',
                theme: 'monokai',
                indentUnit: 2,
                indentWithTabs: false,
                lineWrapping: true
            });

            // Store original code
            originalCode = editor.getValue();
        }

        function runCode() {
            if (!editor) return;
            
            const code = editor.getValue();
            const outputFrame = document.getElementById('outputFrame');
            
            if (outputFrame) {
                const frameDoc = outputFrame.contentDocument || outputFrame.contentWindow.document;
                frameDoc.open();
                frameDoc.write(code);
                frameDoc.close();
            }
        }

        function resetCode() {
            if (!editor || !originalCode) return;
            editor.setValue(originalCode);
            runCode();
        }

        // Auto-run on page load for interactive lessons
        if (editor) {
            setTimeout(runCode, 500);
        }
    </script>
    <script src="js/quiz-submission.js"></script>
    <script src="js/lesson-progress.js"></script>
</body>
</html>

