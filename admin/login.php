<?php
/**
 * Admin Login Page
 * Dedicated login page for admin access - hidden from public navigation
 */

session_start();

// If admin is already logged in, redirect to admin dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

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
            // Prepare statement to fetch user by email - ONLY ADMINS
            $loginQuery = "SELECT id, full_name, email, password, role FROM users WHERE email = ? AND role = 'admin' LIMIT 1";
            $stmt = $conn->prepare($loginQuery);
            
            if ($stmt) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 1) {
                    // Admin user found, verify password
                    $user = $result->fetch_assoc();
                    
                    // Verify password using password_verify()
                    if (password_verify($password, $user['password'])) {
                        // Password is correct - Login successful
                        
                        // Start session and store user data
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['full_name'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['logged_in'] = true;
                        
                        // Regenerate session ID for security
                        session_regenerate_id(true);
                        
                        // Close statement and connection
                        $stmt->close();
                        closeDBConnection($conn);
                        
                        // Redirect to admin dashboard
                        header('Location: index.php');
                        exit();
                    } else {
                        // Password is incorrect
                        $errors['password'] = 'Invalid email or password. Please try again.';
                    }
                } else {
                    // No admin user found with this email
                    $errors['email'] = 'Invalid email or password. Please try again.';
                    // Don't reveal if it's an admin account or not for security
                }
                
                $stmt->close();
            }
            
            closeDBConnection($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - NileTech</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #006994 0%, #00b3b3 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .admin-login-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 40px;
            width: 100%;
            max-width: 450px;
        }
        .admin-login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .admin-login-header h1 {
            color: #006994;
            margin: 0 0 10px 0;
            font-size: 28px;
        }
        .admin-login-header p {
            color: #666;
            margin: 0;
            font-size: 14px;
        }
        .admin-lock-icon {
            font-size: 48px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 15px;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        .form-group input:focus {
            outline: none;
            border-color: #006994;
        }
        .error-message {
            color: #ef4444;
            font-size: 13px;
            margin-top: 5px;
            display: block;
        }
        .btn-admin-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #006994 0%, #00b3b3 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 10px;
        }
        .btn-admin-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 105, 148, 0.3);
        }
        .btn-admin-login:active {
            transform: translateY(0);
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        .back-link a {
            color: #006994;
            text-decoration: none;
            font-size: 14px;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
        .security-notice {
            background: #fff3cd;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 25px;
            font-size: 13px;
            color: #92400e;
        }
    </style>
</head>
<body>
    <div class="admin-login-container">
        <div class="admin-login-header">
            <div class="admin-lock-icon">🔒</div>
            <h1>Admin Login</h1>
            <p>NileTech Learning Platform</p>
        </div>

        <div class="security-notice">
            <strong>⚠️ Admin Access Only:</strong> This page is restricted to administrators only. Unauthorized access is prohibited.
        </div>

        <?php if (!empty($errors)): ?>
            <div style="background: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; border-radius: 5px; margin-bottom: 20px; color: #991b1b;">
                <?php foreach ($errors as $error): ?>
                    <p style="margin: 0 0 5px 0;"><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" autocomplete="off">
            <div class="form-group">
                <label for="email">Admin Email Address *</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="admin@niletech.com"
                    value="<?php echo htmlspecialchars($email); ?>"
                    required
                    autocomplete="email"
                >
                <?php if (isset($errors['email'])): ?>
                    <span class="error-message"><?php echo htmlspecialchars($errors['email']); ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password">Password *</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    required
                    autocomplete="current-password"
                >
                <?php if (isset($errors['password'])): ?>
                    <span class="error-message"><?php echo htmlspecialchars($errors['password']); ?></span>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn-admin-login">Login to Admin Panel</button>
        </form>

        <div class="back-link">
            <a href="../index.php">← Back to Main Site</a>
        </div>
    </div>
</body>
</html>

