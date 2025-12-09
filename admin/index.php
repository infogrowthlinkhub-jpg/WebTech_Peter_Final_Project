<?php
/**
 * Admin Dashboard - Main Page
 * Shows overview statistics and quick actions
 */

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/auth.php';

// Get statistics
$stats = [];

// Total users
$usersStmt = $conn->prepare("SELECT COUNT(*) as total FROM users");
$usersStmt->execute();
$usersResult = $usersStmt->get_result();
$stats['users'] = $usersResult->fetch_assoc()['total'];
$usersStmt->close();

// Total modules
$modulesStmt = $conn->prepare("SELECT COUNT(*) as total FROM modules");
$modulesStmt->execute();
$modulesResult = $modulesStmt->get_result();
$stats['modules'] = $modulesResult->fetch_assoc()['total'];
$modulesStmt->close();

// Total lessons
$lessonsStmt = $conn->prepare("SELECT COUNT(*) as total FROM lessons");
$lessonsStmt->execute();
$lessonsResult = $lessonsStmt->get_result();
$stats['lessons'] = $lessonsResult->fetch_assoc()['total'];
$lessonsStmt->close();

// Total feedback
$feedbackStmt = $conn->prepare("SELECT COUNT(*) as total FROM feedback");
$feedbackStmt->execute();
$feedbackResult = $feedbackStmt->get_result();
$stats['feedback'] = $feedbackResult->fetch_assoc()['total'];
$feedbackStmt->close();

// Total mentors
$mentorsStmt = $conn->prepare("SELECT COUNT(*) as total FROM mentorship");
$mentorsStmt->execute();
$mentorsResult = $mentorsStmt->get_result();
$stats['mentors'] = $mentorsResult->fetch_assoc()['total'];
$mentorsStmt->close();

// Total quiz attempts
$quizStmt = $conn->prepare("SELECT COUNT(*) as total FROM quiz_scores");
$quizStmt->execute();
$quizResult = $quizStmt->get_result();
$stats['quiz_attempts'] = $quizResult->fetch_assoc()['total'];
$quizStmt->close();

// Recent users (last 5)
$recentUsersStmt = $conn->prepare("SELECT id, full_name, email, created_at FROM users ORDER BY created_at DESC LIMIT 5");
$recentUsersStmt->execute();
$recentUsersResult = $recentUsersStmt->get_result();
$recentUsers = [];
while ($row = $recentUsersResult->fetch_assoc()) {
    $recentUsers[] = $row;
}
$recentUsersStmt->close();

// Recent feedback (last 5)
$recentFeedbackStmt = $conn->prepare("SELECT f.id, f.title, f.feedback_type, f.created_at, u.full_name FROM feedback f LEFT JOIN users u ON f.user_id = u.id ORDER BY f.created_at DESC LIMIT 5");
$recentFeedbackStmt->execute();
$recentFeedbackResult = $recentFeedbackStmt->get_result();
$recentFeedback = [];
while ($row = $recentFeedbackResult->fetch_assoc()) {
    $recentFeedback[] = $row;
}
$recentFeedbackStmt->close();

require_once __DIR__ . '/includes/header.php';
?>

<div class="stats-grid">
    <div class="stat-card">
        <h3>Total Users</h3>
        <p class="stat-value"><?php echo number_format($stats['users']); ?></p>
        <a href="users.php" class="btn-admin btn-sm btn-secondary-admin">View All</a>
    </div>
    <div class="stat-card">
        <h3>Modules</h3>
        <p class="stat-value"><?php echo number_format($stats['modules']); ?></p>
        <a href="modules.php" class="btn-admin btn-sm btn-secondary-admin">Manage</a>
    </div>
    <div class="stat-card">
        <h3>Lessons</h3>
        <p class="stat-value"><?php echo number_format($stats['lessons']); ?></p>
        <a href="lessons.php" class="btn-admin btn-sm btn-secondary-admin">Manage</a>
    </div>
    <div class="stat-card">
        <h3>Feedback</h3>
        <p class="stat-value"><?php echo number_format($stats['feedback']); ?></p>
        <a href="feedback.php" class="btn-admin btn-sm btn-secondary-admin">View All</a>
    </div>
    <div class="stat-card">
        <h3>Mentors</h3>
        <p class="stat-value"><?php echo number_format($stats['mentors']); ?></p>
        <a href="mentorship.php" class="btn-admin btn-sm btn-secondary-admin">Manage</a>
    </div>
    <div class="stat-card">
        <h3>Quiz Attempts</h3>
        <p class="stat-value"><?php echo number_format($stats['quiz_attempts']); ?></p>
        <a href="quiz-scores.php" class="btn-admin btn-sm btn-secondary-admin">View All</a>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
    <!-- Recent Users -->
    <div class="admin-card">
        <h2>Recent Users</h2>
        <?php if (empty($recentUsers)): ?>
            <div class="empty-state">
                <p>No users yet.</p>
            </div>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Joined</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentUsers as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Recent Feedback -->
    <div class="admin-card">
        <h2>Recent Feedback</h2>
        <?php if (empty($recentFeedback)): ?>
            <div class="empty-state">
                <p>No feedback yet.</p>
            </div>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Type</th>
                        <th>User</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentFeedback as $fb): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($fb['title']); ?></td>
                            <td><span class="badge badge-info"><?php echo ucfirst(str_replace('_', ' ', $fb['feedback_type'])); ?></span></td>
                            <td><?php echo htmlspecialchars($fb['full_name'] ?? 'Anonymous'); ?></td>
                            <td><?php echo date('M j, Y', strtotime($fb['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php
closeDBConnection($conn);
require_once __DIR__ . '/includes/footer.php';
?>

