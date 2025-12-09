<?php
/**
 * Forgot Password - Request Password Reset
 * Allows users to request a password reset via email
 */

session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$errors = [];
$successMessage = '';
$email = '';

// If user is already logged in, redirect
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    // Validation
    if (empty($email)) {
        $errors['email'] = 'Email address is required.';
    } elseif (!isValidEmail($email)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    
    if (empty($errors)) {
        $conn = getDBConnection();
        
        // Check if user exists
        $stmt = $conn->prepare("SELECT id, full_name FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if ($user) {
            // Generate secure token
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token valid for 1 hour
            
            // Check if password_reset_tokens table exists
            $tableCheck = $conn->query("SHOW TABLES LIKE 'password_reset_tokens'");
            if (!$tableCheck || $tableCheck->num_rows === 0) {
                // Create table if it doesn't exist
                $createTable = "CREATE TABLE IF NOT EXISTS password_reset_tokens (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    user_id INT(11) NOT NULL,
                    token VARCHAR(255) NOT NULL,
                    expires_at TIMESTAMP NOT NULL,
                    used TINYINT(1) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    INDEX idx_token (token),
                    INDEX idx_user_id (user_id),
                    INDEX idx_expires_at (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                $conn->query($createTable);
            }
            
            // Invalidate any existing tokens for this user
            $invalidateStmt = $conn->prepare("UPDATE password_reset_tokens SET used = 1 WHERE user_id = ? AND used = 0");
            $invalidateStmt->bind_param("i", $user['id']);
            $invalidateStmt->execute();
            $invalidateStmt->close();
            
            // Insert new token
            $insertStmt = $conn->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
            $insertStmt->bind_param("iss", $user['id'], $token, $expiresAt);
            
            if ($insertStmt->execute()) {
                // Send password reset email
                $resetLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
                             "://" . $_SERVER['HTTP_HOST'] . 
                             dirname($_SERVER['PHP_SELF']) . 
                             "/reset-password.php?token=" . $token;
                
                $emailSubject = "Password Reset Request - NileTech Learning";
                $emailBody = "
                <!DOCTYPE html>
                <html lang='en'>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #006994 0%, #00b3b3 100%); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
                        .button { display: inline-block; padding: 12px 30px; background: #006994; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                        .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
                        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>🌊 NileTech Learning Platform</h1>
                            <p>Password Reset Request</p>
                        </div>
                        <div class='content'>
                            <p>Hello <strong>{$user['full_name']}</strong>,</p>
                            <p>We received a request to reset your password for your NileTech Learning account.</p>
                            <p>Click the button below to reset your password:</p>
                            <div style='text-align: center;'>
                                <a href='{$resetLink}' class='button'>Reset Password</a>
                            </div>
                            <p>Or copy and paste this link into your browser:</p>
                            <p style='word-break: break-all; color: #006994;'>{$resetLink}</p>
                            <div class='warning'>
                                <strong>⚠️ Security Notice:</strong> This link will expire in 1 hour. If you didn't request this password reset, please ignore this email.
                            </div>
                            <p>Best regards,<br>
                            <strong>NileTech Learning Platform</strong></p>
                        </div>
                        <div class='footer'>
                            <p>This is an automated email from NileTech Learning Platform.<br>
                            © " . date('Y') . " NileTech Learning Website. Empowering youth through digital education.</p>
                        </div>
                    </div>
                </body>
                </html>
                ";
                
                $emailSent = sendEmail($email, $emailSubject, $emailBody);
                
                // Always show success message (don't reveal if email exists or not for security)
                $successMessage = 'If an account with that email exists, a password reset link has been sent. Please check your email.';
            } else {
                $errors['general'] = 'Failed to generate reset token. Please try again.';
            }
            
            $insertStmt->close();
        } else {
            // Don't reveal if email exists (security best practice)
            $successMessage = 'If an account with that email exists, a password reset link has been sent. Please check your email.';
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
    <title>Forgot Password - NileTech Learning</title>
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

    <!-- Forgot Password Section -->
    <section class="auth-section">
        <div class="container">
            <div class="auth-container">
                <div class="auth-header">
                    <h2>Forgot Password?</h2>
                    <p>Enter your email address and we'll send you a link to reset your password</p>
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

                <form method="POST" action="forgot-password.php" class="auth-form">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            placeholder="Enter your email address"
                            value="<?php echo htmlspecialchars($email); ?>"
                            required
                            <?php echo isset($errors['email']) ? 'style="border-color: #ef4444;"' : ''; ?>
                        >
                        <?php if (isset($errors['email'])): ?>
                            <span class="error-message show"><?php echo htmlspecialchars($errors['email']); ?></span>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-primary btn-full">Send Reset Link</button>
                </form>

                <div class="auth-footer">
                    <p>Remember your password? <a href="login.php">Login here</a></p>
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
</body>
</html>

