<?php
/**
 * Admin - Feedback Management (View/Delete)
 */

$pageTitle = 'Feedback Management';
require_once __DIR__ . '/includes/auth.php';

$action = $_GET['action'] ?? 'list';
$feedbackId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    if ($feedbackId > 0) {
        $stmt = $conn->prepare("DELETE FROM feedback WHERE id = ?");
        $stmt->bind_param("i", $feedbackId);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Feedback deleted successfully.';
        } else {
            $_SESSION['error_message'] = 'Failed to delete feedback.';
        }
        $stmt->close();
    }
    header('Location: feedback.php');
    exit;
}

// Get single feedback for viewing
$viewFeedback = null;
if ($action === 'view' && $feedbackId > 0) {
    $stmt = $conn->prepare("SELECT f.*, u.email as user_email FROM feedback f LEFT JOIN users u ON f.user_id = u.id WHERE f.id = ?");
    $stmt->bind_param("i", $feedbackId);
    $stmt->execute();
    $result = $stmt->get_result();
    $viewFeedback = $result->fetch_assoc();
    $stmt->close();
    
    if (!$viewFeedback) {
        $_SESSION['error_message'] = 'Feedback not found.';
        header('Location: feedback.php');
        exit;
    }
}

// Get all feedback for listing
$feedback = [];
$listStmt = $conn->prepare("SELECT f.id, f.title, f.feedback_type, f.user_name, f.email, f.created_at, u.email as user_email FROM feedback f LEFT JOIN users u ON f.user_id = u.id ORDER BY f.created_at DESC");
$listStmt->execute();
$listResult = $listStmt->get_result();
while ($row = $listResult->fetch_assoc()) {
    $feedback[] = $row;
}
$listStmt->close();

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($action === 'list'): ?>
    <div class="admin-card">
        <h2>All Feedback</h2>
        
        <?php if (empty($feedback)): ?>
            <div class="empty-state">
                <p>No feedback found.</p>
            </div>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>User</th>
                        <th>Email</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($feedback as $fb): ?>
                        <tr>
                            <td><?php echo $fb['id']; ?></td>
                            <td><?php echo htmlspecialchars($fb['title'] ?? 'No title'); ?></td>
                            <td>
                                <span class="badge badge-info">
                                    <?php echo ucfirst(str_replace('_', ' ', $fb['feedback_type'])); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($fb['user_name']); ?></td>
                            <td><?php echo htmlspecialchars($fb['email'] ?? $fb['user_email'] ?? 'N/A'); ?></td>
                            <td><?php echo date('M j, Y g:i A', strtotime($fb['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="?action=view&id=<?php echo $fb['id']; ?>" class="btn-admin btn-sm btn-secondary-admin">View</a>
                                    <a href="?action=delete&id=<?php echo $fb['id']; ?>" 
                                       class="btn-admin btn-sm btn-danger-admin"
                                       onclick="return confirm('Are you sure you want to delete this feedback?');">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

<?php elseif ($action === 'view' && $viewFeedback): ?>
    <div class="admin-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>Feedback Details</h2>
            <a href="feedback.php" class="btn-admin btn-secondary-admin">← Back to List</a>
        </div>
        
        <div class="admin-form">
            <div class="form-group">
                <label>Feedback Type</label>
                <div>
                    <span class="badge badge-info">
                        <?php echo ucfirst(str_replace('_', ' ', $viewFeedback['feedback_type'])); ?>
                    </span>
                </div>
            </div>
            
            <div class="form-group">
                <label>Title</label>
                <div style="padding: 10px; background: #f5f5f5; border-radius: 5px;">
                    <?php echo htmlspecialchars($viewFeedback['title'] ?? 'No title'); ?>
                </div>
            </div>
            
            <div class="form-group">
                <label>User Name</label>
                <div style="padding: 10px; background: #f5f5f5; border-radius: 5px;">
                    <?php echo htmlspecialchars($viewFeedback['user_name']); ?>
                </div>
            </div>
            
            <div class="form-group">
                <label>Email</label>
                <div style="padding: 10px; background: #f5f5f5; border-radius: 5px;">
                    <?php echo htmlspecialchars($viewFeedback['email'] ?? $viewFeedback['user_email'] ?? 'N/A'); ?>
                </div>
            </div>
            
            <div class="form-group">
                <label>Message</label>
                <div style="padding: 15px; background: #f5f5f5; border-radius: 5px; white-space: pre-wrap;">
                    <?php echo htmlspecialchars($viewFeedback['message']); ?>
                </div>
            </div>
            
            <div class="form-group">
                <label>Submitted</label>
                <div style="padding: 10px; background: #f5f5f5; border-radius: 5px;">
                    <?php echo date('F j, Y \a\t g:i A', strtotime($viewFeedback['created_at'])); ?>
                </div>
            </div>
            
            <div class="form-actions">
                <a href="feedback.php" class="btn-admin btn-secondary-admin">Back to List</a>
                <a href="?action=delete&id=<?php echo $viewFeedback['id']; ?>" 
                   class="btn-admin btn-danger-admin"
                   onclick="return confirm('Are you sure you want to delete this feedback?');">Delete</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
closeDBConnection($conn);
require_once __DIR__ . '/includes/footer.php';
?>

