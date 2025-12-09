<?php
/**
 * Admin - Mentor Schedule Management
 * Allows admins to set mentor availability schedules
 */

$pageTitle = 'Mentor Schedule Management';
require_once __DIR__ . '/includes/auth.php';

$action = $_GET['action'] ?? 'list';
$mentorId = isset($_GET['mentor_id']) ? (int)$_GET['mentor_id'] : 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add' || $action === 'edit') {
        $availabilityId = isset($_POST['availability_id']) ? (int)$_POST['availability_id'] : 0;
        $mentorName = trim($_POST['mentor_name'] ?? '');
        $dayOfWeek = trim($_POST['day_of_week'] ?? '');
        $startTime = trim($_POST['start_time'] ?? '');
        $endTime = trim($_POST['end_time'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        // Get mentor ID from name
        $mentorStmt = $conn->prepare("SELECT id FROM mentorship WHERE name = ? LIMIT 1");
        $mentorStmt->bind_param("s", $mentorName);
        $mentorStmt->execute();
        $mentorResult = $mentorStmt->get_result();
        $mentor = $mentorResult->fetch_assoc();
        $mentorStmt->close();
        
        if ($mentor) {
            $mentorId = $mentor['id'];
            
            // Check if mentor_availability table exists
            $tableCheck = $conn->query("SHOW TABLES LIKE 'mentor_availability'");
            if (!$tableCheck || $tableCheck->num_rows === 0) {
                // Create table if it doesn't exist
                $createTable = "CREATE TABLE IF NOT EXISTS mentor_availability (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    mentor_id INT(11) NOT NULL,
                    mentor_name VARCHAR(100) NOT NULL,
                    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
                    start_time TIME NOT NULL,
                    end_time TIME NOT NULL,
                    is_active TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    INDEX idx_mentor_id (mentor_id),
                    INDEX idx_mentor_name (mentor_name),
                    INDEX idx_day_of_week (day_of_week)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                $conn->query($createTable);
            }
            
            if ($action === 'edit' && $availabilityId > 0) {
                $stmt = $conn->prepare("UPDATE mentor_availability SET mentor_id = ?, mentor_name = ?, day_of_week = ?, start_time = ?, end_time = ?, is_active = ? WHERE id = ?");
                $stmt->bind_param("issssii", $mentorId, $mentorName, $dayOfWeek, $startTime, $endTime, $isActive, $availabilityId);
            } else {
                $stmt = $conn->prepare("INSERT INTO mentor_availability (mentor_id, mentor_name, day_of_week, start_time, end_time, is_active) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issssi", $mentorId, $mentorName, $dayOfWeek, $startTime, $endTime, $isActive);
            }
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = 'Schedule saved successfully.';
            } else {
                $_SESSION['error_message'] = 'Failed to save schedule.';
            }
            $stmt->close();
        } else {
            $_SESSION['error_message'] = 'Mentor not found.';
        }
        
        header('Location: mentor-schedule.php');
        exit;
    } elseif ($action === 'delete') {
        $availabilityId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($availabilityId > 0) {
            $stmt = $conn->prepare("DELETE FROM mentor_availability WHERE id = ?");
            $stmt->bind_param("i", $availabilityId);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = 'Schedule deleted successfully.';
            } else {
                $_SESSION['error_message'] = 'Failed to delete schedule.';
            }
            $stmt->close();
        }
        header('Location: mentor-schedule.php');
        exit;
    }
}

// Get all mentors
$mentors = [];
$mentorsStmt = $conn->prepare("SELECT id, name FROM mentorship ORDER BY name ASC");
$mentorsStmt->execute();
$mentorsResult = $mentorsStmt->get_result();
while ($row = $mentorsResult->fetch_assoc()) {
    $mentors[] = $row;
}
$mentorsStmt->close();

// Get availability schedules
$schedules = [];
$tableCheck = $conn->query("SHOW TABLES LIKE 'mentor_availability'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    $schedulesStmt = $conn->prepare("SELECT a.*, m.name as mentor_name FROM mentor_availability a LEFT JOIN mentorship m ON a.mentor_id = m.id ORDER BY a.mentor_name, a.day_of_week, a.start_time");
    $schedulesStmt->execute();
    $schedulesResult = $schedulesStmt->get_result();
    while ($row = $schedulesResult->fetch_assoc()) {
        $schedules[] = $row;
    }
    $schedulesStmt->close();
}

// Get schedule to edit
$editSchedule = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $scheduleId = (int)$_GET['id'];
    $editStmt = $conn->prepare("SELECT * FROM mentor_availability WHERE id = ? LIMIT 1");
    $editStmt->bind_param("i", $scheduleId);
    $editStmt->execute();
    $editResult = $editStmt->get_result();
    $editSchedule = $editResult->fetch_assoc();
    $editStmt->close();
}

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($action === 'list'): ?>
    <div class="admin-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>Mentor Availability Schedules</h2>
            <a href="?action=add" class="btn-admin btn-primary-admin">+ Add Schedule</a>
        </div>
        
        <?php if (empty($schedules)): ?>
            <div class="empty-state">
                <p>No availability schedules found. Add schedules to allow students to book appointments.</p>
            </div>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Mentor</th>
                        <th>Day</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schedules as $schedule): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($schedule['mentor_name']); ?></td>
                            <td><?php echo htmlspecialchars($schedule['day_of_week']); ?></td>
                            <td><?php echo date('g:i A', strtotime($schedule['start_time'])); ?> - <?php echo date('g:i A', strtotime($schedule['end_time'])); ?></td>
                            <td>
                                <span class="badge <?php echo $schedule['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo $schedule['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="?action=edit&id=<?php echo $schedule['id']; ?>" class="btn-admin btn-sm btn-secondary-admin">Edit</a>
                                    <a href="?action=delete&id=<?php echo $schedule['id']; ?>" 
                                       class="btn-admin btn-sm btn-danger-admin"
                                       onclick="return confirm('Are you sure you want to delete this schedule?');">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

<?php elseif ($action === 'add' || $action === 'edit'): ?>
    <div class="admin-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2><?php echo $action === 'edit' ? 'Edit' : 'Add'; ?> Availability Schedule</h2>
            <a href="mentor-schedule.php" class="btn-admin btn-secondary-admin">← Back to List</a>
        </div>
        
        <form method="POST" class="admin-form">
            <?php if ($action === 'edit' && $editSchedule): ?>
                <input type="hidden" name="availability_id" value="<?php echo $editSchedule['id']; ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label for="mentor_name">Mentor *</label>
                <select name="mentor_name" id="mentor_name" required>
                    <option value="">Select a mentor</option>
                    <?php foreach ($mentors as $mentor): ?>
                        <option value="<?php echo htmlspecialchars($mentor['name']); ?>" 
                                <?php echo ($editSchedule && $editSchedule['mentor_name'] === $mentor['name']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($mentor['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="day_of_week">Day of Week *</label>
                <select name="day_of_week" id="day_of_week" required>
                    <option value="">Select day</option>
                    <?php 
                    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                    foreach ($days as $day): 
                    ?>
                        <option value="<?php echo $day; ?>" 
                                <?php echo ($editSchedule && $editSchedule['day_of_week'] === $day) ? 'selected' : ''; ?>>
                            <?php echo $day; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="start_time">Start Time *</label>
                <input type="time" name="start_time" id="start_time" 
                       value="<?php echo $editSchedule ? $editSchedule['start_time'] : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="end_time">End Time *</label>
                <input type="time" name="end_time" id="end_time" 
                       value="<?php echo $editSchedule ? $editSchedule['end_time'] : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" value="1" 
                           <?php echo ($editSchedule && $editSchedule['is_active']) || !$editSchedule ? 'checked' : ''; ?>>
                    Active (available for booking)
                </label>
            </div>
            
            <div class="form-actions">
                <a href="mentor-schedule.php" class="btn-admin btn-secondary-admin">Cancel</a>
                <button type="submit" class="btn-admin btn-primary-admin">Save Schedule</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php
closeDBConnection($conn);
require_once __DIR__ . '/includes/footer.php';
?>

