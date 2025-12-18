<?php
/**
 * Admin User Setup Script
 * Ensures the admin user exists with the correct email: peter.admin@nitech.com
 * Run this script to verify/update the admin user in your database
 */

require_once 'config/db.php';

$adminEmail = 'peter.admin@nitech.com';
$adminPassword = 'Admin@123'; // Default password - CHANGE THIS AFTER FIRST LOGIN!
$adminName = 'Peter Admin';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Admin User - NileTech</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }
        .step {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid #667eea;
        }
        .step h2 {
            color: #333;
            margin-top: 0;
        }
        .success {
            background: #10b981;
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }
        .error {
            background: #ef4444;
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }
        .warning {
            background: #f59e0b;
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }
        .info {
            background: #3b82f6;
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }
        code {
            background: #f3f4f6;
            padding: 2px 8px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            color: #d63384;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-success {
            background: #10b981;
        }
        .btn-success:hover {
            background: #059669;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        table th, table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .badge-success {
            background: #10b981;
            color: white;
        }
        .badge-danger {
            background: #ef4444;
            color: white;
        }
        .badge-warning {
            background: #f59e0b;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Admin User Setup</h1>
        <p class="subtitle">Verify and update admin user configuration</p>

<?php

$errors = [];
$success = [];
$warnings = [];

try {
    // Step 1: Test database connection
    echo "<div class='step'><h2>Step 1: Database Connection</h2>";
    
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception("Failed to connect to database. Please check your database configuration.");
    }
    
    echo "<div class='success'>✅ Database connection successful!</div>";
    echo "</div>";
    
    // Step 2: Check if users table exists
    echo "<div class='step'><h2>Step 2: Verify Users Table</h2>";
    
    $tableCheck = $conn->query("SHOW TABLES LIKE 'users'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        throw new Exception("Users table does not exist. Please import database.sql first.");
    }
    
    // Check if role column exists
    $columns = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
    if (!$columns || $columns->num_rows === 0) {
        echo "<div class='warning'>⚠️ Role column missing. Adding it now...</div>";
        $conn->query("ALTER TABLE users ADD COLUMN role ENUM('user', 'admin') DEFAULT 'user' AFTER password, ADD INDEX idx_role (role)");
        echo "<div class='success'>✅ Role column added successfully!</div>";
    } else {
        echo "<div class='success'>✅ Users table structure is correct!</div>";
    }
    
    echo "</div>";
    
    // Step 3: Check existing admin users
    echo "<div class='step'><h2>Step 3: Check Existing Admin Users</h2>";
    
    $adminUsersQuery = "SELECT id, full_name, email, role, created_at FROM users WHERE role = 'admin'";
    $adminUsersResult = $conn->query($adminUsersQuery);
    
    if ($adminUsersResult && $adminUsersResult->num_rows > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th></tr>";
        
        $hasCorrectAdmin = false;
        $wrongAdminUsers = [];
        
        while ($user = $adminUsersResult->fetch_assoc()) {
            $isCorrect = ($user['email'] === $adminEmail);
            $statusBadge = $isCorrect 
                ? '<span class="badge badge-success">Correct</span>' 
                : '<span class="badge badge-danger">Wrong Email</span>';
            
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td>{$user['full_name']}</td>";
            echo "<td><code>{$user['email']}</code></td>";
            echo "<td>{$user['role']}</td>";
            echo "<td>{$statusBadge}</td>";
            echo "</tr>";
            
            if ($isCorrect) {
                $hasCorrectAdmin = true;
            } else {
                $wrongAdminUsers[] = $user;
            }
        }
        
        echo "</table>";
        
        if ($hasCorrectAdmin) {
            echo "<div class='success'>✅ Found admin user with correct email: <code>{$adminEmail}</code></div>";
        } else {
            echo "<div class='warning'>⚠️ No admin user found with correct email. Will create/update now.</div>";
        }
        
        if (!empty($wrongAdminUsers)) {
            echo "<div class='info'>ℹ️ Found " . count($wrongAdminUsers) . " admin user(s) with incorrect email(s). These will be updated.</div>";
        }
    } else {
        echo "<div class='info'>ℹ️ No admin users found. Will create the admin user now.</div>";
    }
    
    echo "</div>";
    
    // Step 4: Create/Update admin user
    echo "<div class='step'><h2>Step 4: Create/Update Admin User</h2>";
    
    // Generate password hash
    $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
    
    // Check if admin user exists
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $checkStmt->bind_param("s", $adminEmail);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $existingUser = $checkResult->fetch_assoc();
    $checkStmt->close();
    
    if ($existingUser) {
        // Update existing user
        $updateStmt = $conn->prepare("UPDATE users SET full_name = ?, password = ?, role = 'admin' WHERE email = ?");
        $updateStmt->bind_param("sss", $adminName, $passwordHash, $adminEmail);
        
        if ($updateStmt->execute()) {
            echo "<div class='success'>✅ Admin user updated successfully!</div>";
            echo "<div class='info'>";
            echo "<strong>Updated User:</strong><br>";
            echo "Name: <code>{$adminName}</code><br>";
            echo "Email: <code>{$adminEmail}</code><br>";
            echo "Role: <code>admin</code><br>";
            echo "Password: <code>{$adminPassword}</code> (Default - CHANGE THIS!)";
            echo "</div>";
            $success[] = "Admin user updated";
        } else {
            throw new Exception("Failed to update admin user: " . $conn->error);
        }
        $updateStmt->close();
    } else {
        // Insert new admin user
        $insertStmt = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'admin')");
        $insertStmt->bind_param("sss", $adminName, $adminEmail, $passwordHash);
        
        if ($insertStmt->execute()) {
            echo "<div class='success'>✅ Admin user created successfully!</div>";
            echo "<div class='info'>";
            echo "<strong>Created User:</strong><br>";
            echo "Name: <code>{$adminName}</code><br>";
            echo "Email: <code>{$adminEmail}</code><br>";
            echo "Role: <code>admin</code><br>";
            echo "Password: <code>{$adminPassword}</code> (Default - CHANGE THIS!)";
            echo "</div>";
            $success[] = "Admin user created";
        } else {
            throw new Exception("Failed to create admin user: " . $conn->error);
        }
        $insertStmt->close();
    }
    
    // Remove old admin users with wrong email (optional - commented out for safety)
    // Uncomment if you want to automatically remove old admin users
    /*
    $oldAdminEmails = ['admin@niletech.com'];
    foreach ($oldAdminEmails as $oldEmail) {
        $deleteStmt = $conn->prepare("DELETE FROM users WHERE email = ? AND role = 'admin'");
        $deleteStmt->bind_param("s", $oldEmail);
        $deleteStmt->execute();
        $deleteStmt->close();
    }
    */
    
    echo "</div>";
    
    // Step 5: Verify final state
    echo "<div class='step'><h2>Step 5: Final Verification</h2>";
    
    $verifyStmt = $conn->prepare("SELECT id, full_name, email, role FROM users WHERE email = ? AND role = 'admin'");
    $verifyStmt->bind_param("s", $adminEmail);
    $verifyStmt->execute();
    $verifyResult = $verifyStmt->get_result();
    $adminUser = $verifyResult->fetch_assoc();
    $verifyStmt->close();
    
    if ($adminUser) {
        echo "<div class='success'>✅ <strong>Admin user verified successfully!</strong></div>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th></tr>";
        echo "<tr>";
        echo "<td>{$adminUser['id']}</td>";
        echo "<td>{$adminUser['full_name']}</td>";
        echo "<td><code>{$adminUser['email']}</code></td>";
        echo "<td><span class='badge badge-success'>{$adminUser['role']}</span></td>";
        echo "</tr>";
        echo "</table>";
        
        echo "<div class='warning'>";
        echo "<strong>⚠️ IMPORTANT SECURITY NOTES:</strong><br>";
        echo "1. Default password is: <code>{$adminPassword}</code><br>";
        echo "2. <strong>CHANGE THIS PASSWORD IMMEDIATELY</strong> after first login!<br>";
        echo "3. Admin email must be exactly: <code>{$adminEmail}</code><br>";
        echo "4. Only this email can access the admin panel";
        echo "</div>";
        
        $success[] = "Admin user verified";
    } else {
        throw new Exception("Failed to verify admin user. Something went wrong.");
    }
    
    echo "</div>";
    
    closeDBConnection($conn);
    
} catch (Exception $e) {
    echo "<div class='error'>❌ <strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
    $errors[] = $e->getMessage();
}

// Summary
echo "<div class='step'><h2>📋 Summary</h2>";

if (empty($errors)) {
    echo "<div class='success'><strong>✅ Setup Complete!</strong></div>";
    echo "<p><strong>Admin Login Credentials:</strong></p>";
    echo "<ul>";
    echo "<li><strong>Email:</strong> <code>{$adminEmail}</code></li>";
    echo "<li><strong>Password:</strong> <code>{$adminPassword}</code> (Default - CHANGE THIS!)</li>";
    echo "<li><strong>Role:</strong> admin</li>";
    echo "</ul>";
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ol>";
    echo "<li>Go to <a href='../pages/login.php' class='btn'>Login Page</a> and log in with the admin credentials</li>";
    echo "<li><strong>CHANGE THE PASSWORD</strong> immediately after first login!</li>";
    echo "<li>Access the <a href='admin/index.php' class='btn btn-success'>Admin Panel</a> (after login)</li>";
    echo "</ol>";
} else {
    echo "<div class='error'><strong>❌ Setup Failed</strong></div>";
    echo "<p>Please fix the errors above and try again.</p>";
    echo "<p><strong>Common Solutions:</strong></p>";
    echo "<ul>";
    echo "<li>Make sure MySQL is running in XAMPP</li>";
    echo "<li>Import <code>database.sql</code> if tables don't exist</li>";
    echo "<li>Check database credentials in <code>config/db.php</code></li>";
    echo "</ul>";
}

echo "</div>";
echo "</div></body></html>";
?>

