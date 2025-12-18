<?php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/db.php';

// Fetch mentor profiles from the database using a prepared statement
$mentors = [];
$conn = getDBConnection();

// Check if image column exists first
$hasImageColumn = false;
try {
    $checkColumn = $conn->query("SHOW COLUMNS FROM mentorship LIKE 'image'");
    $hasImageColumn = ($checkColumn && $checkColumn->num_rows > 0);
} catch (Exception $e) {
    // Column doesn't exist, continue without it
    $hasImageColumn = false;
}

// Build query based on whether image column exists
$query = $hasImageColumn 
    ? 'SELECT name, role, bio, contact, image FROM mentorship ORDER BY name ASC'
    : 'SELECT name, role, bio, contact FROM mentorship ORDER BY name ASC';

$stmt = $conn->prepare($query);

if ($stmt) {
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $mentors[] = $row;
        }
    }
    $stmt->close();
}
closeDBConnection($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NileTech - Mentorship</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Contact Mentor Button */
        .btn-contact-mentor {
            background: var(--gradient-river);
            color: blue;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 105, 148, 0.3);
        }

        .btn-contact-mentor:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 105, 148, 0.4);
            background: linear-gradient(135deg, #008b8b 0%, #00b3b3 100%);
        }

        .btn-contact-mentor:active {
            transform: translateY(0);
        }

        /* Contact Mentor Modal */
        .contact-mentor-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            animation: fadeIn 0.3s ease;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .contact-mentor-modal.active {
            display: flex;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .contact-mentor-modal .modal-content {
            background: white;
            border-radius: 15px;
            padding: 35px;
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
            position: relative;
        }

        .contact-mentor-modal .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }

        .contact-mentor-modal .modal-header h2 {
            margin: 0;
            color: var(--nile-deep);
            font-size: 1.75rem;
            background: var(--gradient-river);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .contact-mentor-modal .close-modal {
            background: none;
            border: none;
            font-size: 2rem;
            color: #666;
            cursor: pointer;
            padding: 0;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .contact-mentor-modal .close-modal:hover {
            background: #f0f0f0;
            color: var(--nile-blue);
            transform: rotate(90deg);
        }

        .contact-mentor-form .form-group {
            margin-bottom: 25px;
        }

        .contact-mentor-form label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-dark);
            font-weight: 600;
            font-size: 0.95rem;
        }

        .contact-mentor-form input,
        .contact-mentor-form textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.3s ease;
            background: #f9f9f9;
        }

        .contact-mentor-form input:focus,
        .contact-mentor-form textarea:focus {
            outline: none;
            border-color: var(--nile-blue);
            background: white;
            box-shadow: 0 0 0 3px rgba(0, 105, 148, 0.1);
        }

        .contact-mentor-form input.error,
        .contact-mentor-form textarea.error {
            border-color: #dc2626;
            background: #fff5f5;
        }

        .contact-mentor-form textarea {
            resize: vertical;
            min-height: 120px;
        }

        .error-message {
            display: none;
            color: #dc2626;
            font-size: 0.875rem;
            margin-top: 5px;
        }

        .error-message.show {
            display: block;
        }

        .success-message {
            display: none;
            background: #d1fae5;
            color: #065f46;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #10b981;
            font-weight: 500;
        }

        .success-message.show {
            display: block;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .contact-mentor-form .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .contact-mentor-form .btn {
            padding: 12px 30px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
        }

        .contact-mentor-form .btn-secondary {
            background: #f0f0f0;
            color: var(--text-dark);
        }

        .contact-mentor-form .btn-secondary:hover {
            background: #e0e0e0;
        }

        .contact-mentor-form .btn-primary {
            background: var(--gradient-river);
            color: white;
            box-shadow: 0 4px 15px rgba(0, 105, 148, 0.3);
        }

        .contact-mentor-form .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 105, 148, 0.4);
        }

        .contact-mentor-form .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        @media (max-width: 768px) {
            .contact-mentor-modal .modal-content {
                padding: 25px;
                max-height: 95vh;
            }

            .contact-mentor-form .form-actions {
                flex-direction: column;
            }

            .contact-mentor-form .btn {
                width: 100%;
            }
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
                <li><a href="index.php#modules">Modules</a></li>
                <li><a href="mentorship.php" class="active">Mentorship</a></li>
                <li><a href="my-bookings.php">My Bookings</a></li>
                <li><a href="feedback.php">Feedback</a></li>
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

    <!-- Animated Water Background for Mentorship Page -->
    <div class="mentorship-water-background">
        <div class="mentorship-water-layer mentorship-water-layer-1"></div>
        <div class="mentorship-water-layer mentorship-water-layer-2"></div>
        <div class="mentorship-water-layer mentorship-water-layer-3"></div>
        <div class="mentorship-water-droplets"></div>
        <div class="mentorship-water-bubbles">
            <div class="mentorship-bubble mentorship-bubble-1"></div>
            <div class="mentorship-bubble mentorship-bubble-2"></div>
            <div class="mentorship-bubble mentorship-bubble-3"></div>
            <div class="mentorship-bubble mentorship-bubble-4"></div>
            <div class="mentorship-bubble mentorship-bubble-5"></div>
        </div>
    </div>

    <section class="mentorship page-mentorship">
        <div class="container">
            <div class="mentorship-header">
                <h2 class="section-title">Meet Our Mentors</h2>
                <p class="mentorship-description">
                    Connect with experienced professionals and peers who are passionate about supporting your digital skills journey.
                    Browse through our mentors and reach out to those who align with your interests and goals.
                </p>
                
                <!-- Mentor Search Bar -->
                <div class="search-container" style="max-width: 600px; margin: 30px auto;">
                    <div class="search-box" style="position: relative;">
                        <input type="text" 
                               id="mentor-search-input" 
                               class="search-input" 
                               placeholder="Search mentors by name, skills, or expertise..."
                               style="width: 100%; padding: 12px 45px 12px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1rem; transition: border-color 0.3s;">
                        <span style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #666;">🔍</span>
                        <div id="mentor-search-results" 
                             class="search-results" 
                             style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #e0e0e0; border-radius: 8px; margin-top: 5px; max-height: 400px; overflow-y: auto; z-index: 1000; box-shadow: 0 4px 12px rgba(0,0,0,0.1);"></div>
                    </div>
                </div>
            </div>

            <?php if (count($mentors) === 0): ?>
                <p class="mentorship-empty">
                    Mentor profiles are not available yet. Please check back soon as we continue to grow the NileTech community.
                </p>
            <?php else: ?>
                <div class="mentors-grid">
                    <?php foreach ($mentors as $mentor): ?>
                        <?php 
                        $isPeter = (strpos(strtolower($mentor['name']), 'peter mangor') !== false);
                        $cardClass = $isPeter ? 'mentor-card mentor-card-featured' : 'mentor-card';
                        ?>
                        <div class="<?php echo $cardClass; ?>" data-mentor-name="<?php echo htmlspecialchars($mentor['name'], ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="mentor-avatar">
                                <?php 
                                $hasImage = isset($mentor['image']) && !empty($mentor['image']) && file_exists($mentor['image']);
                                if ($hasImage): 
                                ?>
                                    <img src="<?php echo htmlspecialchars($mentor['image'], ENT_QUOTES, 'UTF-8'); ?>" 
                                         alt="<?php echo htmlspecialchars($mentor['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                         class="mentor-image">
                                <?php else: ?>
                                    <span>👤</span>
                                <?php endif; ?>
                            </div>
                            <h3 class="mentor-name">
                                <?php echo htmlspecialchars($mentor['name'], ENT_QUOTES, 'UTF-8'); ?>
                            </h3>
                            <p class="mentor-role">
                                <?php echo htmlspecialchars($mentor['role'], ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                            <p class="mentor-bio">
                                <?php echo nl2br(htmlspecialchars($mentor['bio'], ENT_QUOTES, 'UTF-8')); ?>
                            </p>
                            <?php if (!empty($mentor['contact'])): ?>
                                <p class="mentor-contact">
                                    <strong>Contact:</strong>
                                    <?php echo htmlspecialchars($mentor['contact'], ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                            <?php endif; ?>
                            <div style="display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap;">
                                <?php if (isset($mentorIds[$mentor['name']])): ?>
                                <a href="book-mentor.php?mentor_id=<?php echo $mentorIds[$mentor['name']]; ?>" 
                                   class="btn-contact-mentor" 
                                   style="flex: 1; min-width: 150px; text-align: center; text-decoration: none; display: inline-block;">
                                    📅 Book Appointment
                                </a>
                                <?php endif; ?>
                                <button class="btn-contact-mentor" data-mentor-name="<?php echo htmlspecialchars($mentor['name'], ENT_QUOTES, 'UTF-8'); ?>" style="flex: 1; min-width: 150px;">
                                    📧 Send Message
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 NileTech Learning Website. Empowering youth through digital education across South Sudan.</p>
            <p style="margin-top: 10px; font-size: 0.9rem; opacity: 0.8;">🌊 Inspired by the Nile River | 🇸🇸 Proudly South Sudanese</p>
        </div>
    </footer>

    <!-- Contact Mentor Modal -->
    <div class="contact-mentor-modal" id="contactMentorModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Contact Mentor</h2>
                <button class="close-modal" id="closeContactModal">&times;</button>
            </div>
            <form id="contactMentorForm" class="contact-mentor-form">
                <input type="hidden" id="mentorName" name="mentor_name" value="">
                <div class="form-group">
                    <label for="contactSubject">Subject *</label>
                    <input type="text" id="contactSubject" name="subject" required placeholder="What would you like to discuss?">
                    <span class="error-message" id="subjectError"></span>
                </div>
                <div class="form-group">
                    <label for="contactMessage">Message *</label>
                    <textarea id="contactMessage" name="message" rows="6" required placeholder="Tell the mentor about yourself and what you'd like to learn or discuss..."></textarea>
                    <span class="error-message" id="messageError"></span>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="cancelContact">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitContact">Send Message</button>
                </div>
            </form>
        </div>
    </div>

    <script src="js/script.js"></script>
    <script src="js/mentor-contact.js"></script>
    <script src="js/search.js"></script>
    <script src="js/notifications.js"></script>
</body>
</html>


