<?php
/**
 * Admin - Modules Management (CRUD)
 */

$pageTitle = 'Modules Management';
require_once __DIR__ . '/includes/auth.php';

$action = $_GET['action'] ?? 'list';
$moduleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create' || $action === 'edit') {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? '');
        
        $errors = [];
        
        if (empty($name)) {
            $errors[] = 'Module name is required.';
        }
        
        if (empty($slug)) {
            $errors[] = 'Slug is required.';
        } else {
            $slug = strtolower(preg_replace('/[^a-z0-9-]/', '-', $slug));
        }
        
        if (empty($errors)) {
            // Check if slug already exists
            $checkStmt = $conn->prepare("SELECT id FROM modules WHERE slug = ? AND id != ?");
            $checkId = $action === 'edit' ? $moduleId : 0;
            $checkStmt->bind_param("si", $slug, $checkId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $errors[] = 'Slug already exists.';
            }
            $checkStmt->close();
        }
        
        if (empty($errors)) {
            if ($action === 'create') {
                $stmt = $conn->prepare("INSERT INTO modules (name, slug, description, icon) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $name, $slug, $description, $icon);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = 'Module created successfully.';
                    header('Location: modules.php');
                    exit;
                } else {
                    $_SESSION['error_message'] = 'Failed to create module.';
                }
            } else {
                $stmt = $conn->prepare("UPDATE modules SET name = ?, slug = ?, description = ?, icon = ? WHERE id = ?");
                $stmt->bind_param("ssssi", $name, $slug, $description, $icon, $moduleId);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = 'Module updated successfully.';
                    header('Location: modules.php');
                    exit;
                } else {
                    $_SESSION['error_message'] = 'Failed to update module.';
                }
            }
            $stmt->close();
        } else {
            $_SESSION['error_message'] = implode(' ', $errors);
        }
    } elseif ($action === 'delete') {
        if ($moduleId > 0) {
            // Check if module has lessons
            $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM lessons WHERE module_id = ?");
            $checkStmt->bind_param("i", $moduleId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $lessonCount = $checkResult->fetch_assoc()['count'];
            $checkStmt->close();
            
            if ($lessonCount > 0) {
                $_SESSION['error_message'] = "Cannot delete module. It has $lessonCount lesson(s). Delete lessons first.";
            } else {
                $stmt = $conn->prepare("DELETE FROM modules WHERE id = ?");
                $stmt->bind_param("i", $moduleId);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = 'Module deleted successfully.';
                } else {
                    $_SESSION['error_message'] = 'Failed to delete module.';
                }
                $stmt->close();
            }
        }
        header('Location: modules.php');
        exit;
    }
}

// Get module for editing
$editModule = null;
if ($action === 'edit' && $moduleId > 0) {
    $stmt = $conn->prepare("SELECT id, name, slug, description, icon FROM modules WHERE id = ?");
    $stmt->bind_param("i", $moduleId);
    $stmt->execute();
    $result = $stmt->get_result();
    $editModule = $result->fetch_assoc();
    $stmt->close();
    
    if (!$editModule) {
        $_SESSION['error_message'] = 'Module not found.';
        header('Location: modules.php');
        exit;
    }
}

// Get all modules for listing
$modules = [];
$listStmt = $conn->prepare("SELECT m.id, m.name, m.slug, m.description, m.icon, COUNT(l.id) as lesson_count FROM modules m LEFT JOIN lessons l ON m.id = l.module_id GROUP BY m.id ORDER BY m.created_at DESC");
$listStmt->execute();
$listResult = $listStmt->get_result();
while ($row = $listResult->fetch_assoc()) {
    $modules[] = $row;
}
$listStmt->close();

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($action === 'list'): ?>
    <div class="admin-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>All Modules</h2>
            <a href="?action=create" class="btn-admin btn-primary-admin">+ Add New Module</a>
        </div>
        
        <?php if (empty($modules)): ?>
            <div class="empty-state">
                <p>No modules found.</p>
            </div>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Icon</th>
                        <th>Lessons</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($modules as $module): ?>
                        <tr>
                            <td><?php echo $module['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($module['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($module['slug']); ?></td>
                            <td><?php echo htmlspecialchars($module['icon'] ?? '-'); ?></td>
                            <td><?php echo $module['lesson_count']; ?></td>
                            <td><?php echo htmlspecialchars(substr($module['description'] ?? '', 0, 50)) . (strlen($module['description'] ?? '') > 50 ? '...' : ''); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="?action=edit&id=<?php echo $module['id']; ?>" class="btn-admin btn-sm btn-secondary-admin">Edit</a>
                                    <a href="?action=delete&id=<?php echo $module['id']; ?>" 
                                       class="btn-admin btn-sm btn-danger-admin"
                                       onclick="return confirm('Are you sure you want to delete this module? This will also delete all associated lessons.');">Delete</a>
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
        <h2><?php echo $action === 'create' ? 'Create New Module' : 'Edit Module'; ?></h2>
        
        <form method="POST" class="admin-form">
            <div class="form-group">
                <label for="name">Module Name *</label>
                <input type="text" id="name" name="name" 
                       value="<?php echo htmlspecialchars($editModule['name'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="slug">Slug * (URL-friendly identifier, e.g., "computer-literacy")</label>
                <input type="text" id="slug" name="slug" 
                       value="<?php echo htmlspecialchars($editModule['slug'] ?? ''); ?>" required
                       pattern="[a-z0-9-]+" title="Only lowercase letters, numbers, and hyphens">
            </div>
            
            <div class="form-group">
                <label for="icon">Icon (emoji or icon code)</label>
                <input type="text" id="icon" name="icon" 
                       value="<?php echo htmlspecialchars($editModule['icon'] ?? ''); ?>"
                       placeholder="e.g., 💻 or computer">
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($editModule['description'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-admin btn-primary-admin"><?php echo $action === 'create' ? 'Create Module' : 'Update Module'; ?></button>
                <a href="modules.php" class="btn-admin btn-secondary-admin">Cancel</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php
closeDBConnection($conn);
require_once __DIR__ . '/includes/footer.php';
?>

