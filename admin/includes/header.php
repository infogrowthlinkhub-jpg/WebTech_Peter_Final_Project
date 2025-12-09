<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Admin Dashboard'; ?> - NileTech Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../admin/css/admin.css">
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="admin-logo">
                <h2>🌊 NileTech</h2>
                <p>Admin Panel</p>
            </div>
            <nav class="admin-nav">
                <a href="index.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                    <span>📊</span> Dashboard
                </a>
                <a href="users.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                    <span>👥</span> Users
                </a>
                <a href="modules.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'modules.php' ? 'active' : ''; ?>">
                    <span>📚</span> Modules
                </a>
                <a href="lessons.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'lessons.php' ? 'active' : ''; ?>">
                    <span>📖</span> Lessons
                </a>
                <a href="feedback.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'feedback.php' ? 'active' : ''; ?>">
                    <span>💬</span> Feedback
                </a>
                <a href="mentorship.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'mentorship.php' ? 'active' : ''; ?>">
                    <span>🤝</span> Mentorship
                </a>
                <a href="mentor-schedule.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'mentor-schedule.php' ? 'active' : ''; ?>">
                    <span>📅</span> Mentor Schedule
                </a>
                <a href="mentor-bookings.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'mentor-bookings.php' ? 'active' : ''; ?>">
                    <span>📋</span> Bookings
                </a>
                <a href="quiz-scores.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'quiz-scores.php' ? 'active' : ''; ?>">
                    <span>📝</span> Quiz Scores
                </a>
                <a href="../index.php" class="nav-item">
                    <span>🏠</span> Back to Site
                </a>
                <a href="../logout.php" class="nav-item logout">
                    <span>🚪</span> Logout
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <!-- Top Bar -->
            <header class="admin-header">
                <div class="admin-header-content">
                    <h1><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Dashboard'; ?></h1>
                    <div class="admin-user-info">
                        <span>Welcome, <strong><?php echo htmlspecialchars($currentUser['full_name']); ?></strong></span>
                        <span class="role-badge">Admin</span>
                    </div>
                </div>
            </header>

            <!-- Flash Messages -->
            <?php
            if (isset($_SESSION['success_message'])) {
                echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
                unset($_SESSION['success_message']);
            }
            if (isset($_SESSION['error_message'])) {
                echo '<div class="alert alert-error">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
                unset($_SESSION['error_message']);
            }
            ?>

            <!-- Page Content -->
            <div class="admin-content">

