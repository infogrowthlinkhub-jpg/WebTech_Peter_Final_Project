<?php
/**
 * My Bookings - User view of their mentor appointments
 */

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$conn = getDBConnection();

// Get user bookings
$bookings = [];
$tableCheck = $conn->query("SHOW TABLES LIKE 'mentor_bookings'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    $bookingsStmt = $conn->prepare("
        SELECT b.*, m.name as mentor_name, m.role as mentor_role
        FROM mentor_bookings b
        LEFT JOIN mentorship m ON b.mentor_id = m.id
        WHERE b.user_id = ?
        ORDER BY b.booking_date DESC, b.start_time DESC
    ");
    $bookingsStmt->bind_param("i", $currentUserId);
    $bookingsStmt->execute();
    $bookingsResult = $bookingsStmt->get_result();
    while ($row = $bookingsResult->fetch_assoc()) {
        $bookings[] = $row;
    }
    $bookingsStmt->close();
}

closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - NileTech Learning</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .bookings-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .bookings-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        .bookings-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .booking-item {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        .booking-item:hover {
            border-color: #006994;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        .booking-mentor {
            font-size: 1.3rem;
            font-weight: 600;
            color: #006994;
        }
        .booking-status {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        .status-confirmed {
            background: #d1fae5;
            color: #065f46;
        }
        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }
        .status-cancelled {
            background: #f3f4f6;
            color: #374151;
        }
        .status-completed {
            background: #dbeafe;
            color: #1e40af;
        }
        .booking-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        .booking-detail {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .booking-detail-icon {
            font-size: 1.5rem;
        }
        .booking-detail-label {
            font-weight: 600;
            color: #666;
            font-size: 0.9rem;
        }
        .booking-detail-value {
            color: #333;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 20px;
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
                <li><a href="mentorship.php">Mentorship</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="my-bookings.php" class="active">My Bookings</a></li>
                <!-- Notifications Bell -->
                <li style="position: relative;">
                    <a href="#" id="notification-bell" class="nav-link-login" style="position: relative; padding: 8px 12px; text-decoration: none;">
                        🔔
                        <span id="notification-badge" style="display: none; position: absolute; top: 0; right: 0; background: #ef4444; color: white; border-radius: 50%; width: 20px; height: 20px; font-size: 0.7rem; display: flex; align-items: center; justify-content: center; font-weight: bold;">0</span>
                    </a>
                    <div id="notification-dropdown" style="display: none; position: absolute; top: 100%; right: 0; background: white; border: 1px solid #e0e0e0; border-radius: 8px; width: 350px; max-height: 500px; overflow-y: auto; z-index: 1000; box-shadow: 0 4px 12px rgba(0,0,0,0.15); margin-top: 10px;">
                        <div style="padding: 15px; text-align: center; color: #666;">Loading notifications...</div>
                    </div>
                </li>
                <li class="nav-user">
                    <span>Welcome, <?php echo htmlspecialchars($currentUserName, ENT_QUOTES, 'UTF-8'); ?></span>
                </li>
                <li><a href="logout.php" class="nav-link-login">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="bookings-container">
        <div class="bookings-header">
            <h1>My Mentor Appointments</h1>
            <p>View and manage your scheduled mentor sessions</p>
        </div>

        <div class="bookings-card">
            <?php if (empty($bookings)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📅</div>
                    <h3>No Bookings Yet</h3>
                    <p>You haven't booked any mentor appointments yet.</p>
                    <a href="mentorship.php" class="btn btn-primary" style="margin-top: 20px; text-decoration: none; display: inline-block;">Browse Mentors</a>
                </div>
            <?php else: ?>
                <?php foreach ($bookings as $booking): 
                    $statusClass = 'status-' . $booking['status'];
                    $isPast = strtotime($booking['booking_date'] . ' ' . $booking['end_time']) < time();
                ?>
                    <div class="booking-item <?php echo $isPast ? 'past-booking' : ''; ?>">
                        <div class="booking-header">
                            <div>
                                <div class="booking-mentor"><?php echo htmlspecialchars($booking['mentor_name']); ?></div>
                                <?php if (!empty($booking['mentor_role'])): ?>
                                    <p style="color: #666; margin: 5px 0 0 0; font-size: 0.9rem;"><?php echo htmlspecialchars($booking['mentor_role']); ?></p>
                                <?php endif; ?>
                            </div>
                            <span class="booking-status <?php echo $statusClass; ?>">
                                <?php echo ucfirst($booking['status']); ?>
                            </span>
                        </div>
                        
                        <div class="booking-details">
                            <div class="booking-detail">
                                <span class="booking-detail-icon">📅</span>
                                <div>
                                    <div class="booking-detail-label">Date</div>
                                    <div class="booking-detail-value">
                                        <?php echo date('l, F j, Y', strtotime($booking['booking_date'])); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="booking-detail">
                                <span class="booking-detail-icon">🕐</span>
                                <div>
                                    <div class="booking-detail-label">Time</div>
                                    <div class="booking-detail-value">
                                        <?php echo date('g:i A', strtotime($booking['start_time'])); ?> - 
                                        <?php echo date('g:i A', strtotime($booking['end_time'])); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($booking['topic'])): ?>
                            <div class="booking-detail">
                                <span class="booking-detail-icon">💬</span>
                                <div>
                                    <div class="booking-detail-label">Topic</div>
                                    <div class="booking-detail-value"><?php echo htmlspecialchars($booking['topic']); ?></div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="booking-detail">
                                <span class="booking-detail-icon">📝</span>
                                <div>
                                    <div class="booking-detail-label">Requested</div>
                                    <div class="booking-detail-value"><?php echo timeAgo($booking['created_at']); ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($booking['message'])): ?>
                            <div style="margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                                <strong>Your Message:</strong>
                                <p style="margin: 5px 0 0 0; color: #666;"><?php echo nl2br(htmlspecialchars($booking['message'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 NileTech Learning Website. Empowering youth through digital education across South Sudan.</p>
        </div>
    </footer>

    <script src="js/script.js"></script>
    <script src="js/notifications.js"></script>
</body>
</html>

