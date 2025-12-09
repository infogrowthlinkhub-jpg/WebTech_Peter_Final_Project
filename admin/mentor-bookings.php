<?php
/**
 * Admin - Mentor Bookings Management
 * Allows admins/mentors to view and manage booking requests
 */

$pageTitle = 'Mentor Bookings';
require_once __DIR__ . '/includes/auth.php';

$action = $_GET['action'] ?? 'list';
$bookingId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle booking status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $newStatus = trim($_POST['status'] ?? '');
    $bookingId = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
    
    if ($bookingId > 0 && in_array($newStatus, ['pending', 'confirmed', 'rejected', 'cancelled', 'completed'])) {
        // Check if mentor_bookings table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'mentor_bookings'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE mentor_bookings SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $newStatus, $bookingId);
            
            if ($stmt->execute()) {
                // Get booking details for notification
                $bookingStmt = $conn->prepare("SELECT user_id, user_name, mentor_name, booking_date, start_time, end_time FROM mentor_bookings WHERE id = ? LIMIT 1");
                $bookingStmt->bind_param("i", $bookingId);
                $bookingStmt->execute();
                $bookingResult = $bookingStmt->get_result();
                $booking = $bookingResult->fetch_assoc();
                $bookingStmt->close();
                
                if ($booking) {
                    // Create notification for user
                    $statusMessages = [
                        'confirmed' => "Your booking with {$booking['mentor_name']} on " . date('F j, Y', strtotime($booking['booking_date'])) . " has been confirmed!",
                        'rejected' => "Your booking request with {$booking['mentor_name']} has been declined.",
                        'cancelled' => "Your booking with {$booking['mentor_name']} has been cancelled.",
                        'completed' => "Your session with {$booking['mentor_name']} has been marked as completed."
                    ];
                    
                    if (isset($statusMessages[$newStatus])) {
                        createNotification($conn, $booking['user_id'], 'booking_update', $statusMessages[$newStatus]);
                    }
                }
                
                $_SESSION['success_message'] = 'Booking status updated successfully.';
            } else {
                $_SESSION['error_message'] = 'Failed to update booking status.';
            }
            $stmt->close();
        }
    }
    
    header('Location: mentor-bookings.php');
    exit;
}

// Get all bookings
$bookings = [];
$tableCheck = $conn->query("SHOW TABLES LIKE 'mentor_bookings'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    $bookingsStmt = $conn->prepare("
        SELECT b.*, u.email as user_email
        FROM mentor_bookings b
        LEFT JOIN users u ON b.user_id = u.id
        ORDER BY b.booking_date DESC, b.start_time DESC
    ");
    $bookingsStmt->execute();
    $bookingsResult = $bookingsStmt->get_result();
    while ($row = $bookingsResult->fetch_assoc()) {
        $bookings[] = $row;
    }
    $bookingsStmt->close();
}

// Get booking to view
$viewBooking = null;
if ($action === 'view' && $bookingId > 0) {
    $viewStmt = $conn->prepare("SELECT b.*, u.email as user_email FROM mentor_bookings b LEFT JOIN users u ON b.user_id = u.id WHERE b.id = ? LIMIT 1");
    $viewStmt->bind_param("i", $bookingId);
    $viewStmt->execute();
    $viewResult = $viewStmt->get_result();
    $viewBooking = $viewResult->fetch_assoc();
    $viewStmt->close();
    
    if (!$viewBooking) {
        $_SESSION['error_message'] = 'Booking not found.';
        header('Location: mentor-bookings.php');
        exit;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($action === 'list'): ?>
    <div class="admin-card">
        <h2>Mentor Bookings</h2>
        
        <?php if (empty($bookings)): ?>
            <div class="empty-state">
                <p>No bookings found.</p>
            </div>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Student</th>
                        <th>Mentor</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Topic</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): 
                        $statusColors = [
                            'pending' => 'badge-warning',
                            'confirmed' => 'badge-success',
                            'rejected' => 'badge-danger',
                            'cancelled' => 'badge-secondary',
                            'completed' => 'badge-info'
                        ];
                        $statusClass = $statusColors[$booking['status']] ?? 'badge-secondary';
                    ?>
                        <tr>
                            <td><?php echo $booking['id']; ?></td>
                            <td><?php echo htmlspecialchars($booking['user_name']); ?></td>
                            <td><?php echo htmlspecialchars($booking['mentor_name']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($booking['booking_date'])); ?></td>
                            <td><?php echo date('g:i A', strtotime($booking['start_time'])); ?> - <?php echo date('g:i A', strtotime($booking['end_time'])); ?></td>
                            <td><?php echo htmlspecialchars($booking['topic'] ?? '-'); ?></td>
                            <td>
                                <span class="badge <?php echo $statusClass; ?>">
                                    <?php echo ucfirst($booking['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="?action=view&id=<?php echo $booking['id']; ?>" class="btn-admin btn-sm btn-secondary-admin">View</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

<?php elseif ($action === 'view' && $viewBooking): ?>
    <div class="admin-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>Booking Details</h2>
            <a href="mentor-bookings.php" class="btn-admin btn-secondary-admin">← Back to List</a>
        </div>
        
        <div class="admin-form">
            <div class="form-group">
                <label>Student</label>
                <div style="padding: 10px; background: #f5f5f5; border-radius: 5px;">
                    <?php echo htmlspecialchars($viewBooking['user_name']); ?> 
                    (<?php echo htmlspecialchars($viewBooking['user_email'] ?? 'N/A'); ?>)
                </div>
            </div>
            
            <div class="form-group">
                <label>Mentor</label>
                <div style="padding: 10px; background: #f5f5f5; border-radius: 5px;">
                    <?php echo htmlspecialchars($viewBooking['mentor_name']); ?>
                </div>
            </div>
            
            <div class="form-group">
                <label>Date & Time</label>
                <div style="padding: 10px; background: #f5f5f5; border-radius: 5px;">
                    <?php echo date('l, F j, Y', strtotime($viewBooking['booking_date'])); ?><br>
                    <?php echo date('g:i A', strtotime($viewBooking['start_time'])); ?> - <?php echo date('g:i A', strtotime($viewBooking['end_time'])); ?>
                </div>
            </div>
            
            <?php if (!empty($viewBooking['topic'])): ?>
            <div class="form-group">
                <label>Topic</label>
                <div style="padding: 10px; background: #f5f5f5; border-radius: 5px;">
                    <?php echo htmlspecialchars($viewBooking['topic']); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($viewBooking['message'])): ?>
            <div class="form-group">
                <label>Message</label>
                <div style="padding: 10px; background: #f5f5f5; border-radius: 5px; white-space: pre-wrap;">
                    <?php echo htmlspecialchars($viewBooking['message']); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label>Status</label>
                <div style="padding: 10px; background: #f5f5f5; border-radius: 5px;">
                    <span class="badge <?php echo $statusColors[$viewBooking['status']] ?? 'badge-secondary'; ?>">
                        <?php echo ucfirst($viewBooking['status']); ?>
                    </span>
                </div>
            </div>
            
            <div class="form-group">
                <label>Update Status</label>
                <form method="POST" style="display: flex; gap: 10px; align-items: center;">
                    <input type="hidden" name="booking_id" value="<?php echo $viewBooking['id']; ?>">
                    <select name="status" required style="flex: 1; padding: 8px;">
                        <option value="pending" <?php echo $viewBooking['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="confirmed" <?php echo $viewBooking['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="rejected" <?php echo $viewBooking['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="cancelled" <?php echo $viewBooking['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        <option value="completed" <?php echo $viewBooking['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                    <button type="submit" name="update_status" class="btn-admin btn-primary-admin">Update</button>
                </form>
            </div>
            
            <div class="form-actions">
                <a href="mentor-bookings.php" class="btn-admin btn-secondary-admin">Back to List</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
closeDBConnection($conn);
require_once __DIR__ . '/includes/footer.php';
?>

