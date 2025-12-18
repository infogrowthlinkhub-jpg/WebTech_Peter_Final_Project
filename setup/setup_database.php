<?php
/**
 * Database Setup and Verification Script
 * Run this file once to set up and verify your database connection
 */

// Include database configuration
require_once 'config/db.php';

$errors = [];
$success = [];

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Database Setup - NileTech</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { background: #10b981; color: white; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #ef4444; color: white; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #3b82f6; color: white; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .step { background: #f3f4f6; padding: 15px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #667eea; }
        h1 { color: #333; }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>🔧 Database Setup & Verification</h1>";

// Step 1: Test MySQL Connection
echo "<div class='step'><h2>Step 1: Testing MySQL Connection</h2>";

try {
    $testConn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    if ($testConn->connect_error) {
        $errors[] = "MySQL Connection Failed: " . $testConn->connect_error;
        echo "<div class='error'>❌ <strong>Connection Failed:</strong> " . htmlspecialchars($testConn->connect_error) . "</div>";
        echo "<p>Please check:</p><ul><li>XAMPP MySQL is running</li><li>Username: <code>" . DB_USER . "</code></li><li>Password is correct</li></ul>";
    } else {
        $success[] = "MySQL connection successful";
        echo "<div class='success'>✅ MySQL connection successful!</div>";
    }
} catch (Exception $e) {
    $errors[] = "Connection error: " . $e->getMessage();
    echo "<div class='error'>❌ <strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

// Step 2: Check if database exists
echo "</div><div class='step'><h2>Step 2: Checking Database</h2>";

if (isset($testConn) && !$testConn->connect_error) {
    $dbCheck = $testConn->query("SHOW DATABASES LIKE '" . DB_NAME . "'");
    
    if ($dbCheck && $dbCheck->num_rows > 0) {
        $success[] = "Database '" . DB_NAME . "' exists";
        echo "<div class='success'>✅ Database <code>" . DB_NAME . "</code> exists!</div>";
    } else {
        $errors[] = "Database '" . DB_NAME . "' does not exist";
        echo "<div class='error'>❌ Database <code>" . DB_NAME . "</code> does not exist!</div>";
        echo "<div class='info'><strong>Solution:</strong> Import the <code>database.sql</code> file in phpMyAdmin or run:</div>";
        echo "<pre style='background: #f3f4f6; padding: 10px; border-radius: 5px;'>CREATE DATABASE " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;</pre>";
    }
}

// Step 3: Test database connection
echo "</div><div class='step'><h2>Step 3: Testing Database Connection</h2>";

try {
    $conn = getDBConnection();
    
    if ($conn) {
        $success[] = "Database connection successful";
        echo "<div class='success'>✅ Database connection successful!</div>";
        
        // Step 4: Check if tables exist
        echo "</div><div class='step'><h2>Step 4: Checking Tables</h2>";
        
        $tables = ['users', 'modules', 'lessons', 'user_progress', 'feedback', 'mentorship'];
        $missingTables = [];
        
        foreach ($tables as $table) {
            $tableCheck = $conn->query("SHOW TABLES LIKE '$table'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                echo "<div class='success'>✅ Table <code>$table</code> exists</div>";
            } else {
                $missingTables[] = $table;
                echo "<div class='error'>❌ Table <code>$table</code> is missing</div>";
            }
        }
        
        if (empty($missingTables)) {
            $success[] = "All required tables exist";
            echo "<div class='success' style='margin-top: 15px;'><strong>✅ All tables exist!</strong></div>";
        } else {
            $errors[] = "Missing tables: " . implode(', ', $missingTables);
            echo "<div class='error' style='margin-top: 15px;'><strong>❌ Missing tables detected!</strong></div>";
            echo "<div class='info'><strong>Solution:</strong> Import the <code>database.sql</code> file completely in phpMyAdmin.</div>";
        }
        
        // Step 5: Check if users table has data
        echo "</div><div class='step'><h2>Step 5: Checking Data</h2>";
        
        $userCount = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
        $moduleCount = $conn->query("SELECT COUNT(*) as count FROM modules")->fetch_assoc()['count'];
        $lessonCount = $conn->query("SELECT COUNT(*) as count FROM lessons")->fetch_assoc()['count'];
        
        echo "<div class='info'>";
        echo "📊 <strong>Current Data:</strong><br>";
        echo "Users: <code>$userCount</code><br>";
        echo "Modules: <code>$moduleCount</code><br>";
        echo "Lessons: <code>$lessonCount</code>";
        echo "</div>";
        
        // Check for admin user with correct email
        $adminEmail = 'peter.admin@nitech.com';
        $adminCheck = $conn->prepare("SELECT id, full_name, email FROM users WHERE email = ? AND role = 'admin'");
        $adminCheck->bind_param("s", $adminEmail);
        $adminCheck->execute();
        $adminResult = $adminCheck->get_result();
        $adminUser = $adminResult->fetch_assoc();
        $adminCheck->close();
        
        if ($adminUser) {
            echo "<div class='success' style='margin-top: 10px;'>✅ Admin user found: <code>{$adminEmail}</code></div>";
        } else {
            echo "<div class='error' style='margin-top: 10px;'>⚠️ Admin user with email <code>{$adminEmail}</code> not found!</div>";
            echo "<div class='info' style='margin-top: 10px;'>";
            echo "<strong>Solution:</strong> Run <a href='setup-admin-user.php' style='color: white; text-decoration: underline;'><strong>setup-admin-user.php</strong></a> to create/update the admin user.";
            echo "</div>";
        }
        
        if ($moduleCount == 0 || $lessonCount == 0) {
            echo "<div class='error' style='margin-top: 10px;'>⚠️ No modules or lessons found. Please import <code>database.sql</code> to populate data.</div>";
        }
        
        closeDBConnection($conn);
    } else {
        $errors[] = "Failed to connect to database";
        echo "<div class='error'>❌ Failed to connect to database!</div>";
    }
} catch (Exception $e) {
    $errors[] = "Database connection error: " . $e->getMessage();
    echo "<div class='error'>❌ <strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

if (isset($testConn)) {
    $testConn->close();
}

// Summary
echo "</div><div class='step'><h2>📋 Summary</h2>";

if (empty($errors)) {
    echo "<div class='success'><strong>✅ All checks passed! Your database is set up correctly.</strong></div>";
    echo "<p>You can now:</p><ul><li>Go to <a href='../pages/signup.php'>Sign Up</a> to create an account</li><li>Go to <a href='../pages/login.php'>Login</a> if you already have an account</li></ul>";
} else {
    echo "<div class='error'><strong>❌ Issues found. Please fix them before proceeding.</strong></div>";
    echo "<h3>How to Fix:</h3>";
    echo "<ol>";
    echo "<li><strong>Make sure XAMPP MySQL is running</strong><br>Open XAMPP Control Panel and start MySQL</li>";
    echo "<li><strong>Import the database</strong><br>";
    echo "<ul>";
    echo "<li>Open phpMyAdmin (http://localhost/phpmyadmin)</li>";
    echo "<li>Click 'New' to create a database (or select existing)</li>";
    echo "<li>Click 'Import' tab</li>";
    echo "<li>Choose <code>database.sql</code> file</li>";
    echo "<li>Click 'Go' to import</li>";
    echo "</ul></li>";
    echo "<li><strong>Verify database name</strong><br>Make sure the database name in <code>config/db.php</code> matches: <code>" . DB_NAME . "</code></li>";
    echo "</ol>";
}

echo "</div></body></html>";
?>

