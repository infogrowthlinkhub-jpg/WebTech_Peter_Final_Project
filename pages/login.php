<?php
/**
 * NileTech Learning Website - Login Processing
 *
 * - Authenticates users securely
 * - Verifies passwords using password_verify()
 * - Uses role-based access control
 * - Redirects users based on role
 */

session_start();

// Redirect if already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: index.php');
    exit();
}

// Database connection
require_once 'config/db.php';

// Initialize variables
$errors = [];
$email = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validation
    if (empty($email)) {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if (empty($password)) {
        $errors['password'] = 'Password is required.';
    }

    // Proceed if no validation errors
    if (empty($errors)) {
        $conn = getDBConnection();

        if ($conn) {
            $sql = "SELECT id, full_name, email, password, role FROM users WHERE email = ? LIMIT 1";
            $stmt = $conn->prepare($sql);

            if ($stmt) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();

                    if (password_verify($password, $user['password'])) {

                        // Successful login
                        session_regenerate_id(true);

                        $_SESSION['logged_in']  = true;
                        $_SESSION['user_id']    = $user['id'];
                        $_SESSION['user_name']  = $user['full_name'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_role']  = $user['role'];

                        $stmt->close();
                        closeDBConnection($conn);

                        // Role-based redirection
                        // Only redirect to admin panel if user is admin AND has the specific admin email
                        if (
                            $user['role'] === 'admin' &&
                            $user['email'] === 'peter.admin@nitech.com'
                        ) {
                            header('Location: admin/index.php');
                        } else {
                            header('Location: index.php');
                        }
                        exit();

                    } else {
                        $errors['email'] = 'Invalid email or password.';
                        $errors['password'] = 'Invalid email or password.';
                    }
                } else {
                    $errors['email'] = 'Invalid email or password.';
                    $errors['password'] = 'Invalid email or password.';
                }

                $stmt->close();
            } else {
                $errors['general'] = 'Login failed. Please try again later.';
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
    <title>Login - NileTech</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<nav class="navbar">
    <div class="container">
        <h1><a href="index.php">NileTech</a></h1>
        <ul class="nav-menu">
            <li><a href="index.php">Home</a></li>
            <li><a href="signup.php">Sign Up</a></li>
        </ul>
    </div>
</nav>

<section class="auth-section">
    <div class="container">
        <div class="auth-container">
            <h2>Welcome Back</h2>
            <p>Login to your account</p>

            <?php if (isset($errors['general'])): ?>
                <div class="error-box">
                    <?php echo htmlspecialchars($errors['general']); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" novalidate>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email"
                           value="<?php echo htmlspecialchars($email); ?>" required>
                    <?php if (isset($errors['email'])): ?>
                        <small class="error"><?php echo htmlspecialchars($errors['email']); ?></small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
                    <?php if (isset($errors['password'])): ?>
                        <small class="error"><?php echo htmlspecialchars($errors['password']); ?></small>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary btn-full">Login</button>
            </form>

            <p class="auth-footer">
                Don’t have an account? <a href="signup.php">Sign up</a>
            </p>
        </div>
    </div>
</section>

<footer class="footer">
    <p>&copy; 2025 NileTech Learning Website</p>
</footer>

</body>
</html>
