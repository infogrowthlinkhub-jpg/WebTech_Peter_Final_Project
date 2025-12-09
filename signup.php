<?php
/**
 * NileTech Learning Website - Signup Processing
 * 
 * This file handles user registration:
 * - Validates form data
 * - Checks for duplicate emails
 * - Hashes passwords securely
 * - Inserts new users into database
 * - Provides feedback to user
 */

// Start session
session_start();

// Include database connection
require_once 'config/db.php';

// Initialize variables
$errors = [];
$success = false;
$fullName = '';
$email = '';
$role = 'user'; // Default role

// Check if form is submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Sanitize and validate input data
    $fullName = trim($_POST['fullName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    $role = trim($_POST['role'] ?? 'user'); // Default to 'user' if not specified
    
    // Validation: Full Name
    if (empty($fullName)) {
        $errors['fullName'] = 'Full name is required.';
    } elseif (strlen($fullName) < 3) {
        $errors['fullName'] = 'Full name must be at least 3 characters long.';
    } elseif (!preg_match("/^[a-zA-Z\s'-]+$/", $fullName)) {
        $errors['fullName'] = 'Full name can only contain letters, spaces, hyphens, and apostrophes.';
    }
    
    // Validation: Email
    if (empty($email)) {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    
    // Validation: Password
    if (empty($password)) {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors['password'] = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors['password'] = 'Password must contain at least one lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Password must contain at least one number.';
    }
    
    // Validation: Confirm Password
    if (empty($confirmPassword)) {
        $errors['confirmPassword'] = 'Please retype your password.';
    } elseif ($password !== $confirmPassword) {
        $errors['confirmPassword'] = 'Passwords do not match. Please try again.';
    }
    
    // Validation: Role
    if (!in_array($role, ['user', 'admin'])) {
        $role = 'user'; // Default to user if invalid role provided
    }
    
    // If no validation errors, proceed with database operations
    if (empty($errors)) {
        // Get database connection
        $conn = getDBConnection();
        
        if ($conn) {
            // Check if email already exists
            $checkEmailQuery = "SELECT id FROM users WHERE email = ?";
            $stmt = $conn->prepare($checkEmailQuery);
            
            if ($stmt) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    // Email already exists
                    $errors['email'] = 'This email address is already registered. Please use a different email or try logging in.';
                    $stmt->close();
                } else {
                    // Email is available, proceed with registration
                    $stmt->close();
                    
                    // Hash password using PHP's password_hash() with PASSWORD_DEFAULT (bcrypt)
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert new user into database using prepared statement (including role)
                    $insertQuery = "INSERT INTO users (full_name, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())";
                    $insertStmt = $conn->prepare($insertQuery);
                    
                    if ($insertStmt) {
                        $insertStmt->bind_param("ssss", $fullName, $email, $hashedPassword, $role);
                        
                        if ($insertStmt->execute()) {
                            // Registration successful
                            $success = true;
                            
                            // Don't auto-login, let user login manually
                            // Clear form data
                            $fullName = '';
                            $email = '';
                            $role = 'user';
                            
                            // Redirect to login page after 2 seconds
                            header("refresh:2;url=login.php");
                            exit();
                        } else {
                            // Database insertion error
                            $errors['general'] = 'Registration failed. Please try again later.';
                            error_log("Signup error: " . $insertStmt->error);
                        }
                        
                        $insertStmt->close();
                    } else {
                        // Prepared statement error
                        $errors['general'] = 'Registration failed. Please try again later.';
                        error_log("Signup prepare error: " . $conn->error);
                    }
                }
            } else {
                // Prepared statement error for email check
                $errors['general'] = 'Registration failed. Please try again later.';
                error_log("Signup check email prepare error: " . $conn->error);
            }
            
            // Close database connection
            closeDBConnection($conn);
        } else {
            // Database connection failed
            $errors['general'] = 'Database connection failed. Please try again later.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - NileTech Learning Website</title>
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
            </ul>
        </div>
    </nav>

    <!-- Signup Section -->
    <section class="auth-section">
        <div class="container">
            <div class="auth-container">
                <div class="auth-header">
                    <h2>Create Your Account</h2>
                    <p>Join NileTech and start your learning journey today</p>
                </div>

                <!-- Success Message -->
                <?php if ($success): ?>
                    <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; text-align: center; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                        <strong>✓ Registration Successful!</strong><br>
                        <small>Redirecting to login page...</small>
                    </div>
                <?php endif; ?>

                <!-- General Error Message -->
                <?php if (isset($errors['general'])): ?>
                    <div style="background: #fee2e2; color: #dc2626; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; border-left: 4px solid #dc2626;">
                        <strong>⚠ Error:</strong> <?php echo htmlspecialchars($errors['general']); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="signup.php" id="signupForm" class="auth-form" novalidate>
                    <!-- Full Name Field -->
                    <div class="form-group">
                        <label for="fullName">Full Name</label>
                        <input 
                            type="text" 
                            id="fullName" 
                            name="fullName" 
                            placeholder="Enter your full name"
                            value="<?php echo htmlspecialchars($fullName); ?>"
                            required
                            minlength="3"
                            <?php echo isset($errors['fullName']) ? 'style="border-color: #ef4444;"' : ''; ?>
                        >
                        <span class="error-message <?php echo isset($errors['fullName']) ? 'show' : ''; ?>" id="fullNameError">
                            <?php echo isset($errors['fullName']) ? htmlspecialchars($errors['fullName']) : ''; ?>
                        </span>
                    </div>

                    <!-- Email Field -->
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
                        <span class="error-message <?php echo isset($errors['email']) ? 'show' : ''; ?>" id="emailError">
                            <?php echo isset($errors['email']) ? htmlspecialchars($errors['email']) : ''; ?>
                        </span>
                    </div>

                    <!-- Password Field -->
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            placeholder="Enter your password"
                            required
                            minlength="8"
                            <?php echo isset($errors['password']) ? 'style="border-color: #ef4444;"' : ''; ?>
                        >
                        <span class="error-message <?php echo isset($errors['password']) ? 'show' : ''; ?>" id="passwordError">
                            <?php echo isset($errors['password']) ? htmlspecialchars($errors['password']) : ''; ?>
                        </span>
                    </div>

                    <!-- Retype Password Field -->
                    <div class="form-group">
                        <label for="confirmPassword">Retype Password</label>
                        <input 
                            type="password" 
                            id="confirmPassword" 
                            name="confirmPassword" 
                            placeholder="Retype your password"
                            required
                            minlength="8"
                            <?php echo isset($errors['confirmPassword']) ? 'style="border-color: #ef4444;"' : ''; ?>
                        >
                        <span class="error-message <?php echo isset($errors['confirmPassword']) ? 'show' : ''; ?>" id="confirmPasswordError">
                            <?php echo isset($errors['confirmPassword']) ? htmlspecialchars($errors['confirmPassword']) : ''; ?>
                        </span>
                    </div>

                    <!-- Role Selection Field -->
                    <div class="form-group">
                        <label for="role">Account Type</label>
                        <select 
                            id="role" 
                            name="role" 
                            class="form-control"
                            style="width: 100%; padding: 12px 10px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 5px; font-size: 1rem;"
                            required
                            <?php echo isset($errors['role']) ? 'style="border-color: #ef4444;"' : ''; ?>
                        >
                            <option value="user" <?php echo ($role === 'user') ? 'selected' : ''; ?>>Regular User</option>
                            <option value="admin" <?php echo ($role === 'admin') ? 'selected' : ''; ?>>Administrator</option>
                        </select>
                        <small style="display: block; margin-top: -10px; margin-bottom: 10px; color: #666; font-size: 0.9rem;">
                            Select your account type. Admin accounts have full access to manage the platform.
                        </small>
                        <span class="error-message <?php echo isset($errors['role']) ? 'show' : ''; ?>" id="roleError">
                            <?php echo isset($errors['role']) ? htmlspecialchars($errors['role']) : ''; ?>
                        </span>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-primary btn-full">Register</button>
                </form>

                <!-- Link to Login -->
                <div class="auth-footer">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
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


