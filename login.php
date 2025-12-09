<?php
/**
 * NileTech Learning Website - Login Processing
 * 
 * This file handles user authentication:
 * - Validates login credentials
 * - Verifies password using password_verify()
 * - Starts session and stores user data
 * - Redirects to dashboard on success
 */

// Start session
session_start();

// If user is already logged in, redirect to homepage
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: index.php');
    exit();
}

// Include database connection
require_once 'config/db.php';

// Initialize variables
$errors = [];
$email = '';

// Check if form is submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Sanitize and validate input data
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validation: Email
    if (empty($email)) {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    
    // Validation: Password
    if (empty($password)) {
        $errors['password'] = 'Password is required.';
    }
    
    // If no validation errors, proceed with authentication
    if (empty($errors)) {
        // Get database connection
        $conn = getDBConnection();
        
        if ($conn) {
            // Prepare statement to fetch user by email
            $loginQuery = "SELECT id, full_name, email, password, role FROM users WHERE email = ? LIMIT 1";
            $stmt = $conn->prepare($loginQuery);
            
            if ($stmt) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 1) {
                    // User found, verify password
                    $user = $result->fetch_assoc();
                    
                    // Verify password using password_verify()
                    if (password_verify($password, $user['password'])) {
                        // Password is correct - Login successful
                        
                        // Start session and store user data
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['full_name'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_role'] = $user['role'] ?? 'user';
                        $_SESSION['logged_in'] = true;
                        
                        // Regenerate session ID for security
                        session_regenerate_id(true);
                        
                        // Close statement and connection
                        $stmt->close();
                        closeDBConnection($conn);
                        
                        // Redirect based on user role
                        if ($user['role'] === 'admin') {
                            // Redirect admin to admin dashboard
                            header('Location: admin/index.php');
                        } else {
                            // Redirect regular users to homepage
                            header('Location: index.php');
                        }
                        exit();
                    } else {
                        // Password is incorrect
                        $errors['password'] = 'Invalid email or password. Please try again.';
                        // Don't reveal which field is wrong for security
                        $errors['email'] = 'Invalid email or password. Please try again.';
                    }
                } else {
                    // User not found
                    $errors['email'] = 'Invalid email or password. Please try again.';
                    $errors['password'] = 'Invalid email or password. Please try again.';
                }
                
                $stmt->close();
            } else {
                // Prepared statement error
                $errors['general'] = 'Login failed. Please try again later.';
                error_log("Login prepare error: " . $conn->error);
            }
            
            // Close database connection
            closeDBConnection($conn);
        } else {
            // Database connection failed
            $errors['general'] = 'Database connection failed. Please check your database setup. <a href="setup_database.php" style="color: #3b82f6;">Click here to verify database setup</a>.';
            error_log("Login: Database connection failed - " . mysqli_connect_error());
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - NileTech Learning Website</title>
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
                <li><a href="signup.php">Sign Up</a></li>
            </ul>
        </div>
    </nav>

    <!-- Login Section -->
    <section class="auth-section">
        <div class="container">
            <div class="auth-container">
                <div class="auth-header">
                    <h2>Welcome Back</h2>
                    <p>Login to continue your learning journey</p>
                </div>

                <!-- General Error Message -->
                <?php if (isset($errors['general'])): ?>
                    <div style="background: #fee2e2; color: #dc2626; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; border-left: 4px solid #dc2626;">
                        <strong>⚠ Error:</strong> <?php echo htmlspecialchars($errors['general']); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php" id="loginForm" class="auth-form" novalidate>
                    <!-- Email Field -->
                    <div class="form-group">
                        <label for="loginEmail">Email Address</label>
                        <input 
                            type="email" 
                            id="loginEmail" 
                            name="email" 
                            placeholder="Enter your email address"
                            value="<?php echo htmlspecialchars($email); ?>"
                            required
                            <?php echo isset($errors['email']) ? 'style="border-color: #ef4444;"' : ''; ?>
                        >
                        <span class="error-message <?php echo isset($errors['email']) ? 'show' : ''; ?>" id="loginEmailError">
                            <?php echo isset($errors['email']) ? htmlspecialchars($errors['email']) : ''; ?>
                        </span>
                    </div>

                    <!-- Password Field -->
                    <div class="form-group">
                        <label for="loginPassword">Password</label>
                        <input 
                            type="password" 
                            id="loginPassword" 
                            name="password" 
                            placeholder="Enter your password"
                            required
                            <?php echo isset($errors['password']) ? 'style="border-color: #ef4444;"' : ''; ?>
                        >
                        <span class="error-message <?php echo isset($errors['password']) ? 'show' : ''; ?>" id="loginPasswordError">
                            <?php echo isset($errors['password']) ? htmlspecialchars($errors['password']) : ''; ?>
                        </span>
                        <div style="text-align: right; margin-top: 5px;">
                            <a href="forgot-password.php" style="color: #667eea; text-decoration: none; font-size: 0.9rem;">Forgot Password?</a>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-primary btn-full">Login</button>
                </form>

                <!-- Link to Signup -->
                <div class="auth-footer">
                    <p>Don't have an account? <a href="signup.php">Sign up here</a></p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 NileTech Learning Website. Empowering youth through digital education across South Sudan.</p>
            <p style="margin-top: 10px; font-size: 0.9rem; opacity: 0.8;">🌊 Inspired by the Nile River | 🇸🇸 Proudly South Sudanese</p>
        </div>
    </footer>

    <script src="js/script.js"></script>
    <script src="js/auth-validation.js"></script>
</body>
</html>


