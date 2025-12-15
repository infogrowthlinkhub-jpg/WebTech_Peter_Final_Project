<?php
/**
 * Admin - Users Management (CRUD)
 */

$pageTitle = 'Users Management';
require_once __DIR__ . '/includes/auth.php';

$action = $_GET['action'] ?? 'list';
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create' || $action === 'edit') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'user';
        
        $errors = [];
        
        // SECURITY: Only super admin (peter.admin@nitech.com) can promote users to admin
        $currentUserEmail = $currentUser['email'] ?? '';
        $isSuperAdmin = ($currentUserEmail === 'peter.admin@nitech.com');
        
        // If trying to set role to admin, verify super admin access
        if ($role === 'admin' && !$isSuperAdmin) {
            $errors[] = 'Access denied. Only the super admin (peter.admin@nitech.com) can promote users to admin.';
            $role = 'user'; // Force to user if not super admin
        }
        
        // If editing and trying to change role to admin, verify super admin access
        if ($action === 'edit' && $role === 'admin') {
            // Get current user role from database
            $currentRoleStmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
            $currentRoleStmt->bind_param("i", $userId);
            $currentRoleStmt->execute();
            $currentRoleResult = $currentRoleStmt->get_result();
            $currentUserData = $currentRoleResult->fetch_assoc();
            $currentRoleStmt->close();
            
            // If user is currently not admin and we're trying to make them admin
            if (($currentUserData['role'] ?? 'user') !== 'admin' && !$isSuperAdmin) {
                $errors[] = 'Access denied. Only the super admin (peter.admin@nitech.com) can promote users to admin.';
                $role = 'user'; // Force to user if not super admin
            }
        }
        
        if (empty($fullName)) {
            $errors[] = 'Full name is required.';
        }
        
        if (empty($email) || !isValidEmail($email)) {
            $errors[] = 'Valid email is required.';
        }
        
        if ($action === 'create' && empty($password)) {
            $errors[] = 'Password is required for new users.';
        }
        
        if (empty($errors)) {
            // Check if email already exists (for new users or if email changed)
            $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $checkId = $action === 'edit' ? $userId : 0;
            $checkStmt->bind_param("si", $email, $checkId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $errors[] = 'Email already exists.';
            }
            $checkStmt->close();
        }
        
        if (empty($errors)) {
            if ($action === 'create') {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $fullName, $email, $hashedPassword, $role);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = 'User created successfully.';
                    header('Location: users.php');
                    exit;
                } else {
                    $_SESSION['error_message'] = 'Failed to create user.';
                }
            } else {
                if (!empty($password)) {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, password = ?, role = ? WHERE id = ?");
                    $stmt->bind_param("ssssi", $fullName, $email, $hashedPassword, $role, $userId);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, role = ? WHERE id = ?");
                    $stmt->bind_param("sssi", $fullName, $email, $role, $userId);
                }
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = 'User updated successfully.';
                    header('Location: users.php');
                    exit;
                } else {
                    $_SESSION['error_message'] = 'Failed to update user.';
                }
            }
            $stmt->close();
        } else {
            $_SESSION['error_message'] = implode(' ', $errors);
        }
    } elseif ($action === 'delete') {
        if ($userId > 0 && $userId != $currentUser['id']) {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = 'User deleted successfully.';
            } else {
                $_SESSION['error_message'] = 'Failed to delete user.';
            }
            $stmt->close();
        } else {
            $_SESSION['error_message'] = 'Cannot delete your own account or invalid user.';
        }
        header('Location: users.php');
        exit;
    }
}

// Get user for editing
$editUser = null;
if ($action === 'edit' && $userId > 0) {
    $stmt = $conn->prepare("SELECT id, full_name, email, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $editUser = $result->fetch_assoc();
    $stmt->close();
    
    if (!$editUser) {
        $_SESSION['error_message'] = 'User not found.';
        header('Location: users.php');
        exit;
    }
}

// Get all users for listing
$users = [];
$listStmt = $conn->prepare("SELECT id, full_name, email, role, created_at FROM users ORDER BY created_at DESC");
$listStmt->execute();
$listResult = $listStmt->get_result();
while ($row = $listResult->fetch_assoc()) {
    $users[] = $row;
}
$listStmt->close();

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($action === 'list'): ?>
    <div class="admin-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>All Users</h2>
            <a href="?action=create" class="btn-admin btn-primary-admin">+ Add New User</a>
        </div>
        
        <?php if (empty($users)): ?>
            <div class="empty-state">
                <p>No users found.</p>
            </div>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="badge <?php echo $user['role'] === 'admin' ? 'badge-success' : 'badge-info'; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="?action=edit&id=<?php echo $user['id']; ?>" class="btn-admin btn-sm btn-secondary-admin">Edit</a>
                                    <?php if ($user['id'] != $currentUser['id']): ?>
                                        <a href="?action=delete&id=<?php echo $user['id']; ?>" 
                                           class="btn-admin btn-sm btn-danger-admin"
                                           onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
                                    <?php endif; ?>
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
        <h2><?php echo $action === 'create' ? 'Create New User' : 'Edit User'; ?></h2>
        
        <form method="POST" class="admin-form">
            <div class="form-group">
                <label for="full_name">Full Name *</label>
                <input type="text" id="full_name" name="full_name" 
                       value="<?php echo htmlspecialchars($editUser['full_name'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" 
                       value="<?php echo htmlspecialchars($editUser['email'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password <?php echo $action === 'edit' ? '(leave blank to keep current)' : '*'; ?></label>
                <input type="password" id="password" name="password" 
                       <?php echo $action === 'create' ? 'required' : ''; ?>
                       minlength="8">
            </div>
            
            <div class="form-group">
                <label for="role">Role *</label>
                <?php
                // Only super admin can promote users to admin
                $currentUserEmail = $currentUser['email'] ?? '';
                $isSuperAdmin = ($currentUserEmail === 'peter.admin@nitech.com');
                $currentRole = $editUser['role'] ?? 'user';
                ?>
                <select id="role" name="role" required <?php echo (!$isSuperAdmin && $currentRole !== 'admin') ? 'disabled' : ''; ?>>
                    <option value="user" <?php echo $currentRole === 'user' ? 'selected' : ''; ?>>User</option>
                    <option value="admin" <?php echo $currentRole === 'admin' ? 'selected' : ''; ?> <?php echo !$isSuperAdmin ? 'disabled' : ''; ?>>Admin</option>
                </select>
                <?php if (!$isSuperAdmin): ?>
                    <small style="color: #f59e0b; display: block; margin-top: 5px;">
                        ⚠️ Only the super admin (peter.admin@nitech.com) can promote users to admin.
                    </small>
                    <?php if ($currentRole === 'admin'): ?>
                        <input type="hidden" name="role" value="admin">
                    <?php else: ?>
                        <input type="hidden" name="role" value="user">
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-admin btn-primary-admin"><?php echo $action === 'create' ? 'Create User' : 'Update User'; ?></button>
                <a href="users.php" class="btn-admin btn-secondary-admin">Cancel</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php
closeDBConnection($conn);
require_once __DIR__ . '/includes/footer.php';
?>

