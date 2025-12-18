<?php
/**
 * Database Creation Script for Login System
 * This script creates the database and users table if they don't exist
 * Run this once: http://localhost/Final_Project_webTech/create_database.php
 */

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = ''; // Empty for XAMPP default
$db_name = 'webtech_2025A_peter_mayen';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Database - NileTech</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 800px; 
            margin: 50px auto; 
            padding: 20px; 
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success { 
            background: #10b981; 
            color: white; 
            padding: 15px; 
            border-radius: 5px; 
            margin: 10px 0; 
        }
        .error { 
            background: #ef4444; 
            color: white; 
            padding: 15px; 
            border-radius: 5px; 
            margin: 10px 0; 
        }
        .info { 
            background: #3b82f6; 
            color: white; 
            padding: 15px; 
            border-radius: 5px; 
            margin: 10px 0; 
        }
        .step { 
            background: #f9fafb; 
            padding: 15px; 
            border-radius: 5px; 
            margin: 10px 0; 
            border-left: 4px solid #667eea; 
        }
        h1 { color: #333; }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 3px; }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .btn:hover { background: #5568d3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Create Database for Login System</h1>

<?php

$errors = [];
$success = [];

// Step 1: Connect to MySQL (without selecting database)
echo "<div class='step'><h2>Step 1: Connecting to MySQL</h2>";

try {
    $conn = new mysqli($db_host, $db_user, $db_pass);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    echo "<div class='success'>✅ Connected to MySQL successfully!</div>";
    $success[] = "MySQL connection successful";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ <strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<p><strong>Solution:</strong> Make sure XAMPP MySQL is running!</p>";
    $errors[] = $e->getMessage();
    exit;
}

// Step 2: Create database
echo "</div><div class='step'><h2>Step 2: Creating Database</h2>";

if (empty($errors)) {
    $createDbQuery = "CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    
    if ($conn->query($createDbQuery)) {
        echo "<div class='success'>✅ Database <code>$db_name</code> created successfully!</div>";
        $success[] = "Database created";
    } else {
        echo "<div class='error'>❌ Error creating database: " . htmlspecialchars($conn->error) . "</div>";
        $errors[] = "Database creation failed";
    }
}

// Step 3: Select database
echo "</div><div class='step'><h2>Step 3: Selecting Database</h2>";

if (empty($errors)) {
    if ($conn->select_db($db_name)) {
        echo "<div class='success'>✅ Database selected successfully!</div>";
        $success[] = "Database selected";
    } else {
        echo "<div class='error'>❌ Error selecting database: " . htmlspecialchars($conn->error) . "</div>";
        $errors[] = "Database selection failed";
    }
}

// Step 4: Create users table
echo "</div><div class='step'><h2>Step 4: Creating Users Table</h2>";

if (empty($errors)) {
    $createUsersTable = "CREATE TABLE IF NOT EXISTS `users` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `full_name` VARCHAR(100) NOT NULL,
        `email` VARCHAR(100) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `role` ENUM('user', 'admin') DEFAULT 'user',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_email` (`email`),
        INDEX `idx_role` (`role`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($createUsersTable)) {
        echo "<div class='success'>✅ Users table created successfully!</div>";
        $success[] = "Users table created";
    } else {
        echo "<div class='error'>❌ Error creating users table: " . htmlspecialchars($conn->error) . "</div>";
        $errors[] = "Users table creation failed";
    }
}

// Step 5: Verify table structure
echo "</div><div class='step'><h2>Step 5: Verifying Table Structure</h2>";

if (empty($errors)) {
    $checkTable = $conn->query("SHOW TABLES LIKE 'users'");
    
    if ($checkTable && $checkTable->num_rows > 0) {
        echo "<div class='success'>✅ Users table exists!</div>";
        
        // Check columns
        $columns = $conn->query("SHOW COLUMNS FROM users");
        $requiredColumns = ['id', 'full_name', 'email', 'password', 'role', 'created_at'];
        $foundColumns = [];
        
        while ($row = $columns->fetch_assoc()) {
            $foundColumns[] = $row['Field'];
        }
        
        $missingColumns = array_diff($requiredColumns, $foundColumns);
        
        // Add missing role column if needed
        if (in_array('role', $missingColumns)) {
            $conn->query("ALTER TABLE users ADD COLUMN role ENUM('user', 'admin') DEFAULT 'user' AFTER password, ADD INDEX idx_role (role)");
            echo "<div class='success'>✅ Added missing 'role' column!</div>";
            $foundColumns[] = 'role';
            $missingColumns = array_diff($requiredColumns, $foundColumns);
        }
        
        if (empty($missingColumns)) {
            echo "<div class='success'>✅ All required columns exist!</div>";
            echo "<div class='info'>Columns found: " . implode(', ', $foundColumns) . "</div>";
        } else {
            echo "<div class='error'>❌ Missing columns: " . implode(', ', $missingColumns) . "</div>";
        }
    } else {
        echo "<div class='error'>❌ Users table not found!</div>";
    }
}

// Step 6: Test connection using config
echo "</div><div class='step'><h2>Step 6: Testing Connection via Config</h2>";

if (empty($errors)) {
    require_once 'config/db.php';
    
    try {
        $testConn = getDBConnection();
        
        if ($testConn) {
            echo "<div class='success'>✅ Database connection via config works perfectly!</div>";
            $success[] = "Config connection works";
            
            // Test query
            $testQuery = $testConn->query("SELECT COUNT(*) as count FROM users");
            if ($testQuery) {
                $count = $testQuery->fetch_assoc()['count'];
                echo "<div class='info'>📊 Current users in database: <code>$count</code></div>";
            }
            
            // Check for admin user
            $adminEmail = 'peter.admin@nitech.com';
            $adminCheck = $testConn->prepare("SELECT id, full_name FROM users WHERE email = ? AND role = 'admin'");
            $adminCheck->bind_param("s", $adminEmail);
            $adminCheck->execute();
            $adminResult = $adminCheck->get_result();
            $adminUser = $adminResult->fetch_assoc();
            $adminCheck->close();
            
            if ($adminUser) {
                echo "<div class='success'>✅ Admin user found: <code>{$adminEmail}</code></div>";
            } else {
                echo "<div class='warning'>⚠️ Admin user not found. Run <a href='setup-admin-user.php' style='color: #f59e0b; text-decoration: underline;'><strong>setup-admin-user.php</strong></a> to create it.</div>";
            }
            
            closeDBConnection($testConn);
        } else {
            echo "<div class='error'>❌ Connection via config failed!</div>";
        }
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Close connection
if (isset($conn)) {
    $conn->close();
}

// Summary
echo "</div><div class='step'><h2>📋 Summary</h2>";

if (empty($errors)) {
    echo "<div class='success'><strong>✅ Database setup complete!</strong></div>";
    echo "<p><strong>What was created:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Database: <code>$db_name</code></li>";
    echo "<li>✅ Table: <code>users</code></li>";
    echo "<li>✅ All required columns</li>";
    echo "</ul>";
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ol>";
                echo "<li>Go to <a href='../pages/signup.php' class='btn'>Sign Up</a> to create your first account</li>";
    echo "<li>Or import the full <code>database.sql</code> for complete setup (modules, lessons, etc.)</li>";
    echo "</ol>";
} else {
    echo "<div class='error'><strong>❌ Some errors occurred. Please fix them above.</strong></div>";
}

?>

    </div>
</body>
</html>

