<?php
/**
 * Reset Password - Process password reset with token
 */

session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$errors = [];
$successMessage = '';
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$validToken = false;
$userId = null;

// If user is already logged in, redirect
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Validate token
if (!empty($token)) {
    $conn = getDBConnection();
    
    // Check if password_reset_tokens table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'password_reset_tokens'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $stmt = $conn->prepare("
            SELECT prt.user_id, prt.token, prt.expires_at, prt.used 
            FROM password_reset_tokens prt
            WHERE prt.token = ? AND prt.used = 0 AND prt.expires_at > NOW()
            LIMIT 1
        ");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $tokenData = $result->fetch_assoc();
            // Use hash_equals() for secure token comparison to prevent timing attacks
            if (hash_equals($tokenData['token'], $token)) {
                $validToken = true;
                $userId = $tokenData['user_id'];
            } else {
                $errors['token'] = 'Invalid or expired reset token. Please request a new password reset.';
            }
        } else {
            $errors['token'] = 'Invalid or expired reset token. Please request a new password reset.';
        }
        $stmt->close();
    } else {
        $errors['token'] = 'Password reset system not available.';
    }
    
    closeDBConnection($conn);
} else {
    $errors['token'] = 'No reset token provided.';
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($newPassword)) {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($newPassword) < 8) {
        $errors['password'] = 'Password must be at least 8 characters long.';
    }
    
    if (empty($confirmPassword)) {
        $errors['confirm_password'] = 'Please confirm your password.';
    } elseif ($newPassword !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }
    
    if (empty($errors)) {
        $conn = getDBConnection();
        
        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update user password
        $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $updateStmt->bind_param("si", $hashedPassword, $userId);
        
        if ($updateStmt->execute()) {
            // Mark token as used (using user_id for security)
            $markUsedStmt = $conn->prepare("UPDATE password_reset_tokens SET used = 1 WHERE user_id = ? AND token = ?");
            $markUsedStmt->bind_param("is", $userId, $token);
            $markUsedStmt->execute();
            $markUsedStmt->close();
            
            $updateStmt->close();
            closeDBConnection($conn);
            
            $successMessage = 'Your password has been reset successfully! You can now login with your new password.';
            $validToken = false; // Hide form after success
        } else {
            $errors['general'] = 'Failed to reset password. Please try again.';
        }
        
        closeDBConnection($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - NileTech Learning</title>
    <link rel="stylesheet" href="css/style.css">
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
                <li><a href="login.php">Login</a></li>
                <li><a href="signup.php">Sign Up</a></li>
            </ul>
        </div>
    </nav>

    <!-- Reset Password Section -->
    <section class="auth-section">
        <div class="container">
            <div class="auth-container">
                <div class="auth-header">
                    <h2>Reset Your Password</h2>
                    <p><?php echo $validToken ? 'Enter your new password below' : 'Invalid or expired reset link'; ?></p>
                </div>

                <?php if ($successMessage): ?>
                    <div style="background: #d1fae5; color: #065f46; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; border-left: 4px solid #10b981;">
                        <strong>✓ Success:</strong> <?php echo htmlspecialchars($successMessage); ?>
                        <div style="margin-top: 15px;">
                            <a href="login.php" class="btn btn-primary">Go to Login</a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (isset($errors['token']) || isset($errors['general'])): ?>
                    <div style="background: #fee2e2; color: #dc2626; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; border-left: 4px solid #dc2626;">
                        <strong>⚠ Error:</strong> <?php echo htmlspecialchars($errors['token'] ?? $errors['general'] ?? ''); ?>
                        <div style="margin-top: 15px;">
                            <a href="forgot-password.php" class="btn btn-secondary">Request New Reset Link</a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($validToken && empty($successMessage)): ?>
                    <form method="POST" action="reset-password.php?token=<?php echo htmlspecialchars($token); ?>" class="auth-form" id="resetForm">
                        <div class="form-group">
                            <label for="password">New Password</label>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                placeholder="Enter your new password (min. 8 characters)"
                                required
                                minlength="8"
                                <?php echo isset($errors['password']) ? 'style="border-color: #ef4444;"' : ''; ?>
                            >
                            <?php if (isset($errors['password'])): ?>
                                <span class="error-message show"><?php echo htmlspecialchars($errors['password']); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input 
                                type="password" 
                                id="confirm_password" 
                                name="confirm_password" 
                                placeholder="Confirm your new password"
                                required
                                minlength="8"
                                <?php echo isset($errors['confirm_password']) ? 'style="border-color: #ef4444;"' : ''; ?>
                            >
                            <?php if (isset($errors['confirm_password'])): ?>
                                <span class="error-message show"><?php echo htmlspecialchars($errors['confirm_password']); ?></span>
                            <?php endif; ?>
                        </div>

                        <button type="submit" class="btn btn-primary btn-full">Reset Password</button>
                    </form>
                <?php endif; ?>

                <div class="auth-footer">
                    <p><a href="login.php">Back to Login</a> | <a href="forgot-password.php">Request New Reset Link</a></p>
                </div>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 NileTech Learning Website. Empowering youth through digital education across South Sudan.</p>
            <p style="margin-top: 10px; font-size: 0.9rem; opacity: 0.8;">🌊 Inspired by the Nile River | 🇸🇸 Proudly South Sudanese</p>
        </div>
    </footer>

    <script src="js/script.js"></script>
    <script>
        // Password confirmation validation
        document.getElementById('resetForm')?.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
            }
        });
    </script>
</body>
</html>

