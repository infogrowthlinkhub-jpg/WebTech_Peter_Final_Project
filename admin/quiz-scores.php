<?php
/**
 * Admin - Quiz Scores Management (View)
 */

$pageTitle = 'Quiz Scores Management';
require_once __DIR__ . '/includes/auth.php';

$action = $_GET['action'] ?? 'list';
$scoreId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    if ($scoreId > 0) {
        $stmt = $conn->prepare("DELETE FROM quiz_scores WHERE id = ?");
        $stmt->bind_param("i", $scoreId);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Quiz score deleted successfully.';
        } else {
            $_SESSION['error_message'] = 'Failed to delete quiz score.';
        }
        $stmt->close();
    }
    header('Location: quiz-scores.php');
    exit;
}

// Get single score for viewing
$viewScore = null;
if ($action === 'view' && $scoreId > 0) {
    $stmt = $conn->prepare("SELECT qs.*, u.full_name, u.email, l.title as lesson_title, m.name as module_name FROM quiz_scores qs LEFT JOIN users u ON qs.user_id = u.id LEFT JOIN lessons l ON qs.lesson_id = l.id LEFT JOIN modules m ON l.module_id = m.id WHERE qs.id = ?");
    $stmt->bind_param("i", $scoreId);
    $stmt->execute();
    $result = $stmt->get_result();
    $viewScore = $result->fetch_assoc();
    $stmt->close();
    
    if (!$viewScore) {
        $_SESSION['error_message'] = 'Quiz score not found.';
        header('Location: quiz-scores.php');
        exit;
    }
}

// Get all quiz scores for listing
$scores = [];
$listStmt = $conn->prepare("SELECT qs.id, qs.score, qs.total_questions, qs.percentage, qs.passed, qs.created_at, u.full_name, l.title as lesson_title, m.name as module_name FROM quiz_scores qs LEFT JOIN users u ON qs.user_id = u.id LEFT JOIN lessons l ON qs.lesson_id = l.id LEFT JOIN modules m ON l.module_id = m.id ORDER BY qs.created_at DESC");
$listStmt->execute();
$listResult = $listStmt->get_result();
while ($row = $listResult->fetch_assoc()) {
    $scores[] = $row;
}
$listStmt->close();

// Get statistics
$statsStmt = $conn->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN passed = TRUE THEN 1 ELSE 0 END) as passed_count, AVG(percentage) as avg_percentage FROM quiz_scores");
$statsStmt->execute();
$statsResult = $statsStmt->get_result();
$stats = $statsResult->fetch_assoc();
$statsStmt->close();

require_once __DIR__ . '/includes/header.php';
?>

<div class="stats-grid" style="margin-bottom: 30px;">
    <div class="stat-card">
        <h3>Total Attempts</h3>
        <p class="stat-value"><?php echo number_format($stats['total']); ?></p>
    </div>
    <div class="stat-card">
        <h3>Passed</h3>
        <p class="stat-value"><?php echo number_format($stats['passed_count']); ?></p>
    </div>
    <div class="stat-card">
        <h3>Average Score</h3>
        <p class="stat-value"><?php echo number_format($stats['avg_percentage'] ?? 0, 1); ?>%</p>
    </div>
</div>

<?php if ($action === 'list'): ?>
    <div class="admin-card">
        <h2>All Quiz Scores</h2>
        
        <?php if (empty($scores)): ?>
            <div class="empty-state">
                <p>No quiz scores found.</p>
            </div>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Module</th>
                        <th>Lesson</th>
                        <th>Score</th>
                        <th>Percentage</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($scores as $score): ?>
                        <tr>
                            <td><?php echo $score['id']; ?></td>
                            <td><?php echo htmlspecialchars($score['full_name'] ?? 'Unknown'); ?></td>
                            <td><?php echo htmlspecialchars($score['module_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($score['lesson_title'] ?? 'N/A'); ?></td>
                            <td><?php echo $score['score']; ?>/<?php echo $score['total_questions']; ?></td>
                            <td><?php echo number_format($score['percentage'], 1); ?>%</td>
                            <td>
                                <span class="badge <?php echo $score['passed'] ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo $score['passed'] ? 'Passed' : 'Failed'; ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y g:i A', strtotime($score['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="?action=view&id=<?php echo $score['id']; ?>" class="btn-admin btn-sm btn-secondary-admin">View</a>
                                    <a href="?action=delete&id=<?php echo $score['id']; ?>" 
                                       class="btn-admin btn-sm btn-danger-admin"
                                       onclick="return confirm('Are you sure you want to delete this quiz score?');">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

<?php elseif ($action === 'view' && $viewScore): ?>
    <div class="admin-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>Quiz Score Details</h2>
            <a href="quiz-scores.php" class="btn-admin btn-secondary-admin">← Back to List</a>
        </div>
        
        <div class="admin-form">
            <div class="form-group">
                <label>User</label>
                <div style="padding: 10px; background: #f5f5f5; border-radius: 5px;">
                    <?php echo htmlspecialchars($viewScore['full_name'] ?? 'Unknown'); ?>
                    <?php if (!empty($viewScore['email'])): ?>
                        <br><small><?php echo htmlspecialchars($viewScore['email']); ?></small>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="form-group">
                <label>Module</label>
                <div style="padding: 10px; background: #f5f5f5; border-radius: 5px;">
                    <?php echo htmlspecialchars($viewScore['module_name'] ?? 'N/A'); ?>
                </div>
            </div>
            
            <div class="form-group">
                <label>Lesson</label>
                <div style="padding: 10px; background: #f5f5f5; border-radius: 5px;">
                    <?php echo htmlspecialchars($viewScore['lesson_title'] ?? 'N/A'); ?>
                </div>
            </div>
            
            <div class="form-group">
                <label>Score</label>
                <div style="padding: 10px; background: #f5f5f5; border-radius: 5px; font-size: 18px; font-weight: 600;">
                    <?php echo $viewScore['score']; ?> / <?php echo $viewScore['total_questions']; ?>
                    (<?php echo number_format($viewScore['percentage'], 1); ?>%)
                </div>
            </div>
            
            <div class="form-group">
                <label>Status</label>
                <div style="padding: 10px;">
                    <span class="badge <?php echo $viewScore['passed'] ? 'badge-success' : 'badge-danger'; ?>" style="font-size: 14px; padding: 8px 15px;">
                        <?php echo $viewScore['passed'] ? '✓ Passed' : '✗ Failed'; ?>
                    </span>
                </div>
            </div>
            
            <div class="form-group">
                <label>Attempted</label>
                <div style="padding: 10px; background: #f5f5f5; border-radius: 5px;">
                    <?php echo date('F j, Y \a\t g:i A', strtotime($viewScore['created_at'])); ?>
                </div>
            </div>
            
            <div class="form-actions">
                <a href="quiz-scores.php" class="btn-admin btn-secondary-admin">Back to List</a>
                <a href="?action=delete&id=<?php echo $viewScore['id']; ?>" 
                   class="btn-admin btn-danger-admin"
                   onclick="return confirm('Are you sure you want to delete this quiz score?');">Delete</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
closeDBConnection($conn);
require_once __DIR__ . '/includes/footer.php';
?>

