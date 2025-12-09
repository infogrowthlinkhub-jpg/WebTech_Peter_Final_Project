<?php
/**
 * Admin - Mentorship Management (CRUD)
 */

$pageTitle = 'Mentorship Management';
require_once __DIR__ . '/includes/auth.php';

$action = $_GET['action'] ?? 'list';
$mentorId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create' || $action === 'edit') {
        $name = trim($_POST['name'] ?? '');
        $role = trim($_POST['role'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $image = trim($_POST['image'] ?? '');
        
        $errors = [];
        
        if (empty($name)) {
            $errors[] = 'Mentor name is required.';
        }
        
        if (!empty($email) && !isValidEmail($email)) {
            $errors[] = 'Invalid email address.';
        }
        
        if (empty($errors)) {
            if ($action === 'create') {
                $stmt = $conn->prepare("INSERT INTO mentorship (name, role, bio, contact, email, image) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssss", $name, $role, $bio, $contact, $email, $image);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = 'Mentor created successfully.';
                    header('Location: mentorship.php');
                    exit;
                } else {
                    $_SESSION['error_message'] = 'Failed to create mentor.';
                }
            } else {
                $stmt = $conn->prepare("UPDATE mentorship SET name = ?, role = ?, bio = ?, contact = ?, email = ?, image = ? WHERE id = ?");
                $stmt->bind_param("ssssssi", $name, $role, $bio, $contact, $email, $image, $mentorId);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = 'Mentor updated successfully.';
                    header('Location: mentorship.php');
                    exit;
                } else {
                    $_SESSION['error_message'] = 'Failed to update mentor.';
                }
            }
            $stmt->close();
        } else {
            $_SESSION['error_message'] = implode(' ', $errors);
        }
    } elseif ($action === 'delete') {
        if ($mentorId > 0) {
            $stmt = $conn->prepare("DELETE FROM mentorship WHERE id = ?");
            $stmt->bind_param("i", $mentorId);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = 'Mentor deleted successfully.';
            } else {
                $_SESSION['error_message'] = 'Failed to delete mentor.';
            }
            $stmt->close();
        }
        header('Location: mentorship.php');
        exit;
    }
}

// Get mentor for editing
$editMentor = null;
if ($action === 'edit' && $mentorId > 0) {
    $stmt = $conn->prepare("SELECT id, name, role, bio, contact, email, image FROM mentorship WHERE id = ?");
    $stmt->bind_param("i", $mentorId);
    $stmt->execute();
    $result = $stmt->get_result();
    $editMentor = $result->fetch_assoc();
    $stmt->close();
    
    if (!$editMentor) {
        $_SESSION['error_message'] = 'Mentor not found.';
        header('Location: mentorship.php');
        exit;
    }
}

// Get all mentors for listing
$mentors = [];
$listStmt = $conn->prepare("SELECT id, name, role, email, contact, created_at FROM mentorship ORDER BY created_at DESC");
$listStmt->execute();
$listResult = $listStmt->get_result();
while ($row = $listResult->fetch_assoc()) {
    $mentors[] = $row;
}
$listStmt->close();

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($action === 'list'): ?>
    <div class="admin-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>All Mentors</h2>
            <a href="?action=create" class="btn-admin btn-primary-admin">+ Add New Mentor</a>
        </div>
        
        <?php if (empty($mentors)): ?>
            <div class="empty-state">
                <p>No mentors found.</p>
            </div>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Email</th>
                        <th>Contact</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mentors as $mentor): ?>
                        <tr>
                            <td><?php echo $mentor['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($mentor['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($mentor['role'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($mentor['email'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($mentor['contact'] ?? '-'); ?></td>
                            <td><?php echo date('M j, Y', strtotime($mentor['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="?action=edit&id=<?php echo $mentor['id']; ?>" class="btn-admin btn-sm btn-secondary-admin">Edit</a>
                                    <a href="?action=delete&id=<?php echo $mentor['id']; ?>" 
                                       class="btn-admin btn-sm btn-danger-admin"
                                       onclick="return confirm('Are you sure you want to delete this mentor?');">Delete</a>
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
        <h2><?php echo $action === 'create' ? 'Create New Mentor' : 'Edit Mentor'; ?></h2>
        
        <form method="POST" class="admin-form">
            <div class="form-group">
                <label for="name">Mentor Name *</label>
                <input type="text" id="name" name="name" 
                       value="<?php echo htmlspecialchars($editMentor['name'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="role">Role/Title</label>
                <input type="text" id="role" name="role" 
                       value="<?php echo htmlspecialchars($editMentor['role'] ?? ''); ?>"
                       placeholder="e.g., Senior Software Engineer">
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" 
                       value="<?php echo htmlspecialchars($editMentor['email'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="contact">Contact Information</label>
                <input type="text" id="contact" name="contact" 
                       value="<?php echo htmlspecialchars($editMentor['contact'] ?? ''); ?>"
                       placeholder="e.g., LinkedIn profile, phone, etc.">
            </div>
            
            <div class="form-group">
                <label for="image">Image Path/URL</label>
                <input type="text" id="image" name="image" 
                       value="<?php echo htmlspecialchars($editMentor['image'] ?? ''); ?>"
                       placeholder="e.g., images/mentors/mentor.jpg">
            </div>
            
            <div class="form-group">
                <label for="bio">Bio</label>
                <textarea id="bio" name="bio" rows="6"><?php echo htmlspecialchars($editMentor['bio'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-admin btn-primary-admin"><?php echo $action === 'create' ? 'Create Mentor' : 'Update Mentor'; ?></button>
                <a href="mentorship.php" class="btn-admin btn-secondary-admin">Cancel</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php
closeDBConnection($conn);
require_once __DIR__ . '/includes/footer.php';
?>

