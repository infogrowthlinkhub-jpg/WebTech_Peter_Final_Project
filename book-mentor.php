<?php
/**
 * Book Mentor - Student interface to book mentor appointments
 */

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$mentorId = isset($_GET['mentor_id']) ? (int)$_GET['mentor_id'] : 0;
$mentorName = isset($_GET['mentor_name']) ? trim($_GET['mentor_name']) : '';

$errors = [];
$successMessage = '';

// Get mentor information
$conn = getDBConnection();
$mentor = null;

if ($mentorId > 0) {
    $stmt = $conn->prepare("SELECT * FROM mentorship WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $mentorId);
    $stmt->execute();
    $result = $stmt->get_result();
    $mentor = $result->fetch_assoc();
    $stmt->close();
} elseif (!empty($mentorName)) {
    $stmt = $conn->prepare("SELECT * FROM mentorship WHERE name = ? LIMIT 1");
    $stmt->bind_param("s", $mentorName);
    $stmt->execute();
    $result = $stmt->get_result();
    $mentor = $result->fetch_assoc();
    $stmt->close();
}

if (!$mentor) {
    header('Location: mentorship.php');
    exit;
}

$mentorId = $mentor['id'];
$mentorName = $mentor['name'];

// Get mentor availability
$availability = [];
$tableCheck = $conn->query("SHOW TABLES LIKE 'mentor_availability'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    $availStmt = $conn->prepare("SELECT * FROM mentor_availability WHERE mentor_id = ? AND is_active = 1 ORDER BY day_of_week, start_time");
    $availStmt->bind_param("i", $mentorId);
    $availStmt->execute();
    $availResult = $availStmt->get_result();
    while ($row = $availResult->fetch_assoc()) {
        $availability[] = $row;
    }
    $availStmt->close();
}

// Get existing bookings for this mentor
$existingBookings = [];
$bookingsCheck = $conn->query("SHOW TABLES LIKE 'mentor_bookings'");
if ($bookingsCheck && $bookingsCheck->num_rows > 0) {
    $bookingsStmt = $conn->prepare("
        SELECT booking_date, start_time, end_time, status 
        FROM mentor_bookings 
        WHERE mentor_id = ? AND status IN ('pending', 'confirmed') AND booking_date >= CURDATE()
        ORDER BY booking_date, start_time
    ");
    $bookingsStmt->bind_param("i", $mentorId);
    $bookingsStmt->execute();
    $bookingsResult = $bookingsStmt->get_result();
    while ($row = $bookingsResult->fetch_assoc()) {
        $existingBookings[] = $row;
    }
    $bookingsStmt->close();
}

// Handle booking submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    $bookingDate = trim($_POST['booking_date'] ?? '');
    $startTime = trim($_POST['start_time'] ?? '');
    $endTime = trim($_POST['end_time'] ?? '');
    $topic = trim($_POST['topic'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Validation
    if (empty($bookingDate)) {
        $errors['booking_date'] = 'Please select a date.';
    } elseif (strtotime($bookingDate) < strtotime('today')) {
        $errors['booking_date'] = 'Cannot book appointments in the past.';
    }
    
    if (empty($startTime)) {
        $errors['start_time'] = 'Please select a start time.';
    }
    
    if (empty($endTime)) {
        $errors['end_time'] = 'Please select an end time.';
    } elseif ($startTime >= $endTime) {
        $errors['end_time'] = 'End time must be after start time.';
    }
    
    if (empty($errors)) {
        // Check if mentor_bookings table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'mentor_bookings'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            $createTable = "CREATE TABLE IF NOT EXISTS mentor_bookings (
                id INT(11) NOT NULL AUTO_INCREMENT,
                user_id INT(11) NOT NULL,
                user_name VARCHAR(100) NOT NULL,
                user_email VARCHAR(100) NOT NULL,
                mentor_id INT(11) NOT NULL,
                mentor_name VARCHAR(100) NOT NULL,
                booking_date DATE NOT NULL,
                start_time TIME NOT NULL,
                end_time TIME NOT NULL,
                topic VARCHAR(200),
                message TEXT,
                status ENUM('pending', 'confirmed', 'rejected', 'cancelled', 'completed') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_user_id (user_id),
                INDEX idx_mentor_id (mentor_id),
                INDEX idx_booking_date (booking_date),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $conn->query($createTable);
        }
        
        // Check for conflicts
        $conflictStmt = $conn->prepare("
            SELECT id FROM mentor_bookings 
            WHERE mentor_id = ? AND booking_date = ? 
            AND status IN ('pending', 'confirmed')
            AND (
                (start_time <= ? AND end_time > ?) OR
                (start_time < ? AND end_time >= ?) OR
                (start_time >= ? AND end_time <= ?)
            )
            LIMIT 1
        ");
        $conflictStmt->bind_param("issssssss", $mentorId, $bookingDate, $startTime, $startTime, $endTime, $endTime, $startTime, $endTime);
        $conflictStmt->execute();
        $conflictResult = $conflictStmt->get_result();
        
        if ($conflictResult->num_rows > 0) {
            $errors['general'] = 'This time slot is already booked. Please choose another time.';
        } else {
            // Get user email
            $userEmail = $_SESSION['user_email'] ?? '';
            if (empty($userEmail)) {
                $emailStmt = $conn->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
                $emailStmt->bind_param("i", $currentUserId);
                $emailStmt->execute();
                $emailResult = $emailStmt->get_result();
                if ($emailRow = $emailResult->fetch_assoc()) {
                    $userEmail = $emailRow['email'];
                }
                $emailStmt->close();
            }
            
            // Create booking
            $insertStmt = $conn->prepare("
                INSERT INTO mentor_bookings (user_id, user_name, user_email, mentor_id, mentor_name, booking_date, start_time, end_time, topic, message, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $insertStmt->bind_param("isssisssss", 
                $currentUserId, 
                $currentUserName, 
                $userEmail, 
                $mentorId, 
                $mentorName, 
                $bookingDate, 
                $startTime, 
                $endTime, 
                $topic, 
                $message
            );
            
            if ($insertStmt->execute()) {
                $bookingId = $conn->insert_id;
                $insertStmt->close();
                
                // Send notification to mentor
                $notificationMessage = "New booking request from {$currentUserName} for {$bookingDate} at " . date('g:i A', strtotime($startTime)) . " - " . date('g:i A', strtotime($endTime)) . (!empty($topic) ? " (Topic: {$topic})" : "");
                
                // Get mentor user ID if they have an account, otherwise send email
                $mentorUserStmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                if ($mentor['email']) {
                    $mentorUserStmt->bind_param("s", $mentor['email']);
                    $mentorUserStmt->execute();
                    $mentorUserResult = $mentorUserStmt->get_result();
                    if ($mentorUser = $mentorUserResult->fetch_assoc()) {
                        createNotification($conn, $mentorUser['id'], 'booking_request', $notificationMessage);
                    }
                }
                $mentorUserStmt->close();
                
                // Send email to mentor
                if (!empty($mentor['email']) && isValidEmail($mentor['email'])) {
                    $emailSubject = "New Booking Request - NileTech";
                    $emailBody = "
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset='UTF-8'>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                            .header { background: linear-gradient(135deg, #006994 0%, #00b3b3 100%); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
                            .booking-details { background: white; padding: 20px; border-left: 4px solid #006994; margin: 20px 0; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h1>🌊 NileTech Learning Platform</h1>
                                <p>New Booking Request</p>
                            </div>
                            <div class='content'>
                                <p>Hello <strong>{$mentorName}</strong>,</p>
                                <p>You have received a new booking request:</p>
                                <div class='booking-details'>
                                    <p><strong>Student:</strong> {$currentUserName}</p>
                                    <p><strong>Date:</strong> " . date('F j, Y', strtotime($bookingDate)) . "</p>
                                    <p><strong>Time:</strong> " . date('g:i A', strtotime($startTime)) . " - " . date('g:i A', strtotime($endTime)) . "</p>
                                    " . (!empty($topic) ? "<p><strong>Topic:</strong> " . htmlspecialchars($topic) . "</p>" : "") . "
                                    " . (!empty($message) ? "<p><strong>Message:</strong><br>" . nl2br(htmlspecialchars($message)) . "</p>" : "") . "
                                </div>
                                <p>Please log in to the admin panel to confirm or reject this booking.</p>
                            </div>
                        </div>
                    </body>
                    </html>
                    ";
                    sendEmail($mentor['email'], $emailSubject, $emailBody);
                }
                
                // Create notification for user
                createNotification($conn, $currentUserId, 'booking_submitted', "Your booking request with {$mentorName} has been submitted and is pending confirmation.");
                
                $successMessage = 'Booking request submitted successfully! The mentor will be notified and can confirm your appointment.';
            } else {
                $errors['general'] = 'Failed to create booking. Please try again.';
            }
        }
        $conflictStmt->close();
    }
}

// Generate available time slots for next 4 weeks
$availableSlots = generateAvailableSlots($availability, $existingBookings);

closeDBConnection($conn);

/**
 * Generate available time slots based on availability and existing bookings
 */
function generateAvailableSlots($availability, $existingBookings) {
    $slots = [];
    $startDate = new DateTime('today');
    $endDate = new DateTime('+4 weeks');
    
    // Create a map of existing bookings by date and time
    $bookedSlots = [];
    foreach ($existingBookings as $booking) {
        $dateKey = $booking['booking_date'];
        $timeKey = $booking['start_time'] . '-' . $booking['end_time'];
        if (!isset($bookedSlots[$dateKey])) {
            $bookedSlots[$dateKey] = [];
        }
        $bookedSlots[$dateKey][] = $timeKey;
    }
    
    // Generate slots for each day
    $currentDate = clone $startDate;
    while ($currentDate <= $endDate) {
        $dayName = $currentDate->format('l'); // Monday, Tuesday, etc.
        $dateStr = $currentDate->format('Y-m-d');
        
        // Find availability for this day
        foreach ($availability as $avail) {
            if ($avail['day_of_week'] === $dayName) {
                // Generate 30-minute slots
                $start = new DateTime($dateStr . ' ' . $avail['start_time']);
                $end = new DateTime($dateStr . ' ' . $avail['end_time']);
                $slotStart = clone $start;
                
                while ($slotStart < $end) {
                    $slotEnd = clone $slotStart;
                    $slotEnd->modify('+30 minutes');
                    
                    if ($slotEnd <= $end) {
                        $timeKey = $slotStart->format('H:i:s') . '-' . $slotEnd->format('H:i:s');
                        $isBooked = isset($bookedSlots[$dateStr]) && in_array($timeKey, $bookedSlots[$dateStr]);
                        
                        if (!$isBooked) {
                            $slots[] = [
                                'date' => $dateStr,
                                'date_display' => $currentDate->format('M j, Y'),
                                'day' => $dayName,
                                'start_time' => $slotStart->format('H:i:s'),
                                'end_time' => $slotEnd->format('H:i:s'),
                                'start_display' => $slotStart->format('g:i A'),
                                'end_display' => $slotEnd->format('g:i A')
                            ];
                        }
                    }
                    
                    $slotStart->modify('+30 minutes');
                }
            }
        }
        
        $currentDate->modify('+1 day');
    }
    
    return $slots;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment with <?php echo htmlspecialchars($mentorName); ?> - NileTech</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .booking-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .mentor-info-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .booking-form-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .calendar-view {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .time-slot {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            background: white;
        }
        .time-slot:hover {
            border-color: #006994;
            background: #f0f8ff;
        }
        .time-slot.selected {
            border-color: #006994;
            background: #006994;
            color: white;
        }
        .time-slot.booked {
            border-color: #ccc;
            background: #f5f5f5;
            color: #999;
            cursor: not-allowed;
        }
        .date-group {
            margin-bottom: 30px;
        }
        .date-header {
            font-size: 1.2rem;
            font-weight: 600;
            color: #006994;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
        }
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #006994;
        }
        .selected-slot-info {
            background: #e0f2fe;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #006994;
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
                <li class="nav-user">
                    <span>Welcome, <?php echo htmlspecialchars($currentUserName, ENT_QUOTES, 'UTF-8'); ?></span>
                </li>
                <li><a href="logout.php" class="nav-link-login">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="booking-container">
        <!-- Mentor Info -->
        <div class="mentor-info-card">
            <h2 style="margin-top: 0; color: #006994;">Book Appointment with <?php echo htmlspecialchars($mentorName); ?></h2>
            <?php if (!empty($mentor['role'])): ?>
                <p style="color: #666; font-size: 1.1rem; margin: 10px 0;"><strong>Role:</strong> <?php echo htmlspecialchars($mentor['role']); ?></p>
            <?php endif; ?>
            <?php if (!empty($mentor['bio'])): ?>
                <p style="color: #666; margin: 10px 0;"><?php echo nl2br(htmlspecialchars($mentor['bio'])); ?></p>
            <?php endif; ?>
        </div>

        <?php if ($successMessage): ?>
            <div style="background: #d1fae5; color: #065f46; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; border-left: 4px solid #10b981;">
                <strong>✓ Success:</strong> <?php echo htmlspecialchars($successMessage); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($errors['general'])): ?>
            <div style="background: #fee2e2; color: #dc2626; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; border-left: 4px solid #dc2626;">
                <strong>⚠ Error:</strong> <?php echo htmlspecialchars($errors['general']); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($availability)): ?>
            <div class="booking-form-card">
                <p style="color: #666; text-align: center; padding: 40px;">
                    This mentor has not set their availability yet. Please check back later or contact them directly.
                </p>
                <div style="text-align: center;">
                    <a href="mentorship.php" class="btn btn-primary">← Back to Mentors</a>
                </div>
            </div>
        <?php else: ?>
            <!-- Booking Form -->
            <div class="booking-form-card">
                <h3 style="margin-top: 0; color: #006994;">Select Available Time Slot</h3>
                
                <form method="POST" id="bookingForm">
                    <input type="hidden" name="book_appointment" value="1">
                    
                    <!-- Calendar/Time Slot Selection -->
                    <div id="timeSlotsContainer">
                        <?php
                        $currentDate = '';
                        foreach ($availableSlots as $slot):
                            if ($currentDate !== $slot['date']):
                                if ($currentDate !== ''):
                                    echo '</div></div>'; // Close previous date group
                                endif;
                                $currentDate = $slot['date'];
                        ?>
                            <div class="date-group">
                                <div class="date-header"><?php echo $slot['date_display']; ?> (<?php echo $slot['day']; ?>)</div>
                                <div class="calendar-view">
                        <?php endif; ?>
                                    <div class="time-slot" 
                                         data-date="<?php echo $slot['date']; ?>"
                                         data-start="<?php echo $slot['start_time']; ?>"
                                         data-end="<?php echo $slot['end_time']; ?>"
                                         onclick="selectTimeSlot(this)">
                                        <?php echo $slot['start_display']; ?> - <?php echo $slot['end_display']; ?>
                                    </div>
                        <?php endforeach; ?>
                                </div>
                            </div>
                    </div>

                    <!-- Selected Slot Info -->
                    <div id="selectedSlotInfo" class="selected-slot-info" style="display: none;">
                        <strong>Selected:</strong> <span id="selectedSlotText"></span>
                    </div>

                    <!-- Hidden inputs for selected slot -->
                    <input type="hidden" name="booking_date" id="booking_date" required>
                    <input type="hidden" name="start_time" id="start_time" required>
                    <input type="hidden" name="end_time" id="end_time" required>

                    <!-- Additional Booking Details -->
                    <div class="form-group">
                        <label for="topic">Topic/Subject (Optional)</label>
                        <input type="text" id="topic" name="topic" 
                               placeholder="What would you like to discuss?"
                               value="<?php echo htmlspecialchars($_POST['topic'] ?? ''); ?>">
                        <?php if (isset($errors['topic'])): ?>
                            <span style="color: #ef4444; font-size: 0.9rem;"><?php echo htmlspecialchars($errors['topic']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="message">Message to Mentor (Optional)</label>
                        <textarea id="message" name="message" rows="4" 
                                  placeholder="Tell the mentor about what you'd like to discuss..."><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                        <?php if (isset($errors['message'])): ?>
                            <span style="color: #ef4444; font-size: 0.9rem;"><?php echo htmlspecialchars($errors['message']); ?></span>
                        <?php endif; ?>
                    </div>

                    <div style="display: flex; gap: 15px; margin-top: 30px;">
                        <a href="mentorship.php" class="btn btn-secondary" style="text-decoration: none;">Cancel</a>
                        <button type="submit" class="btn btn-primary" id="submitBtn" disabled>Book Appointment</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 NileTech Learning Website. Empowering youth through digital education across South Sudan.</p>
        </div>
    </footer>

    <script src="js/script.js"></script>
    <script>
        let selectedSlot = null;

        function selectTimeSlot(element) {
            if (element.classList.contains('booked')) {
                return;
            }

            // Remove previous selection
            document.querySelectorAll('.time-slot').forEach(slot => {
                slot.classList.remove('selected');
            });

            // Select new slot
            element.classList.add('selected');
            selectedSlot = {
                date: element.dataset.date,
                start: element.dataset.start,
                end: element.dataset.end,
                text: element.textContent.trim()
            };

            // Update hidden inputs
            document.getElementById('booking_date').value = selectedSlot.date;
            document.getElementById('start_time').value = selectedSlot.start;
            document.getElementById('end_time').value = selectedSlot.end;

            // Show selected slot info
            const dateObj = new Date(selectedSlot.date);
            const dateStr = dateObj.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            document.getElementById('selectedSlotText').textContent = dateStr + ' at ' + element.textContent.trim();
            document.getElementById('selectedSlotInfo').style.display = 'block';

            // Enable submit button
            document.getElementById('submitBtn').disabled = false;
        }

        // Form validation
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            if (!selectedSlot) {
                e.preventDefault();
                alert('Please select a time slot.');
                return false;
            }
        });
    </script>
</body>
</html>

