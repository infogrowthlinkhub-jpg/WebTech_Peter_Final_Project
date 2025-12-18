<?php
/**
 * NileTech Learning Website - Signup Processing
 *
 * Rules:
 * - All users register as regular users
 * - No admin signup allowed
 * - Admin roles are assigned later by a super admin
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

// Force role (VERY IMPORTANT)
$role = 'user';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Sanitize inputs
    $fullName = trim($_POST['fullName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';

    // Validate Full Name
    if (empty($fullName)) {
        $errors['fullName'] = 'Full name is required.';
    } elseif (strlen($fullName) < 3) {
        $errors['fullName'] = 'Full name must be at least 3 characters long.';
    } elseif (!preg_match("/^[a-zA-Z\s'-]+$/", $fullName)) {
        $errors['fullName'] = 'Full name can only contain letters, spaces, hyphens, and apostrophes.';
    }

    // Validate Email
    if (empty($email)) {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    // Validate Password
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

    // Confirm Password
    if (empty($confirmPassword)) {
        $errors['confirmPassword'] = 'Please retype your password.';
    } elseif ($password !== $confirmPassword) {
        $errors['confirmPassword'] = 'Passwords do not match.';
    }

    // If no validation errors, proceed
    if (empty($errors)) {

        $conn = getDBConnection();

        if ($conn) {
            // Check for duplicate email
            $checkQuery = "SELECT id FROM users WHERE email = ?";
            $stmt = $conn->prepare($checkQuery);

            if ($stmt) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $errors['email'] = 'This email is already registered.';
                    $stmt->close();
                } else {
                    $stmt->close();

                    // Hash password
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                    // Insert user (ROLE IS ALWAYS USER)
                    $insertQuery = "
                        INSERT INTO users (full_name, email, password, role, created_at)
                        VALUES (?, ?, ?, ?, NOW())
                    ";
                    $insertStmt = $conn->prepare($insertQuery);

                    if ($insertStmt) {
                        $insertStmt->bind_param(
                            "ssss",
                            $fullName,
                            $email,
                            $hashedPassword,
                            $role
                        );

                        if ($insertStmt->execute()) {
                            $success = true;
                            $fullName = '';
                            $email = '';

                            header("refresh:2;url=login.php");
                            exit();
                        } else {
                            $errors['general'] = 'Registration failed. Please try again.';
                            error_log($insertStmt->error);
                        }

                        $insertStmt->close();
                    } else {
                        $errors['general'] = 'Registration failed. Please try again.';
                    }
                }
            } else {
                $errors['general'] = 'Registration failed. Please try again.';
            }

            closeDBConnection($conn);
        } else {
            $errors['general'] = 'Database connection failed.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign Up - NileTech</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<nav class="navbar">
    <div class="container">
        <h1><a href="index.php">NileTech</a></h1>
        <ul>
            <li><a href="login.php">Login</a></li>
        </ul>
    </div>
</nav>

<section class="auth-section">
    <div class="container">
        <div class="auth-container">

            <h2>Create Your Account</h2>
            <p>Join NileTech and start learning today</p>

            <?php if ($success): ?>
                <div class="success-message">
                    <strong>✓ Registration successful!</strong><br>
                    Redirecting to login...
                </div>
            <?php endif; ?>

            <?php if (isset($errors['general'])): ?>
                <div class="error-message show">
                    <?php echo htmlspecialchars($errors['general']); ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="signupForm" novalidate>

                <div class="form-group">
                    <label for="fullName">Full Name</label>
                    <input type="text" id="fullName" name="fullName"
                           value="<?php echo htmlspecialchars($fullName); ?>" required>
                    <span class="error-message show" id="fullNameError">
                        <?php echo $errors['fullName'] ?? ''; ?>
                    </span>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email"
                           value="<?php echo htmlspecialchars($email); ?>" required>
                    <span class="error-message show" id="emailError">
                        <?php echo $errors['email'] ?? ''; ?>
                    </span>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                    <span class="error-message show" id="passwordError">
                        <?php echo $errors['password'] ?? ''; ?>
                    </span>
                </div>

                <div class="form-group">
                    <label for="confirmPassword">Confirm Password</label>
                    <input type="password" id="confirmPassword" name="confirmPassword" required>
                    <span class="error-message show" id="confirmPasswordError">
                        <?php echo $errors['confirmPassword'] ?? ''; ?>
                    </span>
                </div>

                <button type="submit" class="btn btn-primary btn-full">
                    Register
                </button>
            </form>

            <p class="auth-footer">
                Already have an account? <a href="login.php">Login</a>
            </p>

        </div>
    </div>
</section>

<footer class="footer">
    <p>&copy; 2025 NileTech Learning Website</p>
</footer>

<script src="js/script.js"></script>
<script src="js/auth-validation.js"></script>
</body>
</html>
