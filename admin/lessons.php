<?php
/**
 * Admin - Lessons Management (CRUD)
 */

$pageTitle = 'Lessons Management';
require_once __DIR__ . '/includes/auth.php';

$action = $_GET['action'] ?? 'list';
$lessonId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create' || $action === 'edit') {
        $moduleId = (int)($_POST['module_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $content = $_POST['content'] ?? '';
        $lessonOrder = (int)($_POST['lesson_order'] ?? 0);
        $lessonType = $_POST['lesson_type'] ?? 'text';
        $codeExample = $_POST['code_example'] ?? '';
        $expectedOutput = $_POST['expected_output'] ?? '';
        
        $errors = [];
        
        if ($moduleId <= 0) {
            $errors[] = 'Module is required.';
        }
        
        if (empty($title)) {
            $errors[] = 'Lesson title is required.';
        }
        
        if (empty($slug)) {
            $errors[] = 'Slug is required.';
        } else {
            $slug = strtolower(preg_replace('/[^a-z0-9-]/', '-', $slug));
        }
        
        if (empty($errors)) {
            // Check if slug already exists for this module
            $checkStmt = $conn->prepare("SELECT id FROM lessons WHERE module_id = ? AND slug = ? AND id != ?");
            $checkId = $action === 'edit' ? $lessonId : 0;
            $checkStmt->bind_param("isi", $moduleId, $slug, $checkId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $errors[] = 'Slug already exists for this module.';
            }
            $checkStmt->close();
        }
        
        if (empty($errors)) {
            if ($action === 'create') {
                $stmt = $conn->prepare("INSERT INTO lessons (module_id, title, slug, content, lesson_order, lesson_type, code_example, expected_output) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ississss", $moduleId, $title, $slug, $content, $lessonOrder, $lessonType, $codeExample, $expectedOutput);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = 'Lesson created successfully.';
                    header('Location: lessons.php');
                    exit;
                } else {
                    $_SESSION['error_message'] = 'Failed to create lesson.';
                }
            } else {
                $stmt = $conn->prepare("UPDATE lessons SET module_id = ?, title = ?, slug = ?, content = ?, lesson_order = ?, lesson_type = ?, code_example = ?, expected_output = ? WHERE id = ?");
                $stmt->bind_param("ississssi", $moduleId, $title, $slug, $content, $lessonOrder, $lessonType, $codeExample, $expectedOutput, $lessonId);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = 'Lesson updated successfully.';
                    header('Location: lessons.php');
                    exit;
                } else {
                    $_SESSION['error_message'] = 'Failed to update lesson.';
                }
            }
            $stmt->close();
        } else {
            $_SESSION['error_message'] = implode(' ', $errors);
        }
    } elseif ($action === 'delete') {
        if ($lessonId > 0) {
            $stmt = $conn->prepare("DELETE FROM lessons WHERE id = ?");
            $stmt->bind_param("i", $lessonId);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = 'Lesson deleted successfully.';
            } else {
                $_SESSION['error_message'] = 'Failed to delete lesson.';
            }
            $stmt->close();
        }
        header('Location: lessons.php');
        exit;
    }
}

// Get lesson for editing
$editLesson = null;
if ($action === 'edit' && $lessonId > 0) {
    $stmt = $conn->prepare("SELECT id, module_id, title, slug, content, lesson_order, lesson_type, code_example, expected_output FROM lessons WHERE id = ?");
    $stmt->bind_param("i", $lessonId);
    $stmt->execute();
    $result = $stmt->get_result();
    $editLesson = $result->fetch_assoc();
    $stmt->close();
    
    if (!$editLesson) {
        $_SESSION['error_message'] = 'Lesson not found.';
        header('Location: lessons.php');
        exit;
    }
}

// Get all modules for dropdown
$modules = [];
$modulesStmt = $conn->prepare("SELECT id, name FROM modules ORDER BY name");
$modulesStmt->execute();
$modulesResult = $modulesStmt->get_result();
while ($row = $modulesResult->fetch_assoc()) {
    $modules[] = $row;
}
$modulesStmt->close();

// Get all lessons for listing
$lessons = [];
$listStmt = $conn->prepare("SELECT l.id, l.title, l.slug, l.lesson_order, l.lesson_type, m.name as module_name FROM lessons l LEFT JOIN modules m ON l.module_id = m.id ORDER BY m.name, l.lesson_order");
$listStmt->execute();
$listResult = $listStmt->get_result();
while ($row = $listResult->fetch_assoc()) {
    $lessons[] = $row;
}
$listStmt->close();

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($action === 'list'): ?>
    <div class="admin-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>All Lessons</h2>
            <a href="?action=create" class="btn-admin btn-primary-admin">+ Add New Lesson</a>
        </div>
        
        <?php if (empty($lessons)): ?>
            <div class="empty-state">
                <p>No lessons found.</p>
            </div>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Module</th>
                        <th>Order</th>
                        <th>Type</th>
                        <th>Slug</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lessons as $lesson): ?>
                        <tr>
                            <td><?php echo $lesson['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($lesson['title']); ?></strong></td>
                            <td><?php echo htmlspecialchars($lesson['module_name'] ?? 'N/A'); ?></td>
                            <td><?php echo $lesson['lesson_order']; ?></td>
                            <td><span class="badge badge-info"><?php echo ucfirst($lesson['lesson_type']); ?></span></td>
                            <td><?php echo htmlspecialchars($lesson['slug']); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="?action=edit&id=<?php echo $lesson['id']; ?>" class="btn-admin btn-sm btn-secondary-admin">Edit</a>
                                    <a href="?action=delete&id=<?php echo $lesson['id']; ?>" 
                                       class="btn-admin btn-sm btn-danger-admin"
                                       onclick="return confirm('Are you sure you want to delete this lesson?');">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

<?php elseif ($action === 'create' || $action === 'edit'): ?>
    <div class="admin-card">
        <h2><?php echo $action === 'create' ? 'Create New Lesson' : 'Edit Lesson'; ?></h2>
        
        <form method="POST" class="admin-form">
            <div class="form-group">
                <label for="module_id">Module *</label>
                <select id="module_id" name="module_id" required>
                    <option value="">Select a module</option>
                    <?php foreach ($modules as $module): ?>
                        <option value="<?php echo $module['id']; ?>" 
                                <?php echo ($editLesson['module_id'] ?? '') == $module['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($module['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="title">Lesson Title *</label>
                <input type="text" id="title" name="title" 
                       value="<?php echo htmlspecialchars($editLesson['title'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="slug">Slug * (URL-friendly identifier)</label>
                <input type="text" id="slug" name="slug" 
                       value="<?php echo htmlspecialchars($editLesson['slug'] ?? ''); ?>" required
                       pattern="[a-z0-9-]+" title="Only lowercase letters, numbers, and hyphens">
            </div>
            
            <div class="form-group">
                <label for="lesson_order">Lesson Order *</label>
                <input type="number" id="lesson_order" name="lesson_order" 
                       value="<?php echo $editLesson['lesson_order'] ?? 0; ?>" required min="0">
            </div>
            
            <div class="form-group">
                <label for="lesson_type">Lesson Type *</label>
                <select id="lesson_type" name="lesson_type" required>
                    <option value="text" <?php echo ($editLesson['lesson_type'] ?? 'text') === 'text' ? 'selected' : ''; ?>>Text</option>
                    <option value="interactive" <?php echo ($editLesson['lesson_type'] ?? '') === 'interactive' ? 'selected' : ''; ?>>Interactive</option>
                    <option value="video" <?php echo ($editLesson['lesson_type'] ?? '') === 'video' ? 'selected' : ''; ?>>Video</option>
                    <option value="quiz" <?php echo ($editLesson['lesson_type'] ?? '') === 'quiz' ? 'selected' : ''; ?>>Quiz</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="content">Content (HTML supported)</label>
                <textarea id="content" name="content" rows="15" style="font-family: monospace;"><?php echo htmlspecialchars($editLesson['content'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="code_example">Code Example (optional)</label>
                <textarea id="code_example" name="code_example" rows="5" style="font-family: monospace;"><?php echo htmlspecialchars($editLesson['code_example'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="expected_output">Expected Output (optional)</label>
                <textarea id="expected_output" name="expected_output" rows="3" style="font-family: monospace;"><?php echo htmlspecialchars($editLesson['expected_output'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-admin btn-primary-admin"><?php echo $action === 'create' ? 'Create Lesson' : 'Update Lesson'; ?></button>
                <a href="lessons.php" class="btn-admin btn-secondary-admin">Cancel</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php
closeDBConnection($conn);
require_once __DIR__ . '/includes/footer.php';
?>

