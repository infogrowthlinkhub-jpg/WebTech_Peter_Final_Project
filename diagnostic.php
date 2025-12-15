<?php
/**
 * Diagnostic Page - Helps identify the "Not Found" issue
 * Access this file directly: http://localhost/Final_Project_webTech_Peter/diagnostic.php
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NileTech - Diagnostic Page</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .box { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #006994; }
        h2 { color: #333; border-bottom: 2px solid #006994; padding-bottom: 10px; }
        .success { color: #10b981; font-weight: bold; }
        .error { color: #ef4444; font-weight: bold; }
        .info { color: #3b82f6; }
        ul { line-height: 1.8; }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        .btn { display: inline-block; padding: 12px 24px; background: #006994; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
        .btn:hover { background: #005577; }
    </style>
</head>
<body>
    <div class="box">
        <h1>🔍 NileTech Diagnostic Page</h1>
        <p class="info">If you can see this page, PHP and Apache are working correctly!</p>
    </div>

    <div class="box">
        <h2>✅ Server Information</h2>
        <ul>
            <li><strong>PHP Version:</strong> <?php echo phpversion(); ?></li>
            <li><strong>Server Software:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?></li>
            <li><strong>Document Root:</strong> <code><?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'N/A'; ?></code></li>
            <li><strong>Current Script:</strong> <code><?php echo __FILE__; ?></code></li>
            <li><strong>Request URI:</strong> <code><?php echo $_SERVER['REQUEST_URI'] ?? 'N/A'; ?></code></li>
            <li><strong>Script Name:</strong> <code><?php echo $_SERVER['SCRIPT_NAME'] ?? 'N/A'; ?></code></li>
        </ul>
    </div>

    <div class="box">
        <h2>📁 File System Check</h2>
        <ul>
            <?php
            $files = [
                'index.php' => 'Main homepage',
                'login.php' => 'Login page',
                'signup.php' => 'Signup page',
                'config/db.php' => 'Database configuration',
                'config/session.php' => 'Session configuration',
                '.htaccess' => 'Apache configuration'
            ];
            
            foreach ($files as $file => $description) {
                $exists = file_exists($file);
                $status = $exists ? '<span class="success">✓ EXISTS</span>' : '<span class="error">✗ NOT FOUND</span>';
                echo "<li><strong>$file</strong> ($description): $status</li>";
            }
            ?>
        </ul>
    </div>

    <div class="box">
        <h2>🌐 URL Configuration</h2>
        <p><strong>Correct URLs to access your site:</strong></p>
        <ul>
            <li><a href="index.php" target="_blank">http://localhost/Final_Project_webTech_Peter/index.php</a></li>
            <li><a href="/Final_Project_webTech_Peter/" target="_blank">http://localhost/Final_Project_webTech_Peter/</a></li>
            <li><a href="login.php" target="_blank">http://localhost/Final_Project_webTech_Peter/login.php</a></li>
        </ul>
        <p class="info"><strong>Note:</strong> Make sure you're using the exact folder name: <code>Final_Project_webTech_Peter</code></p>
    </div>

    <div class="box">
        <h2>🔧 Apache Modules</h2>
        <?php
        if (function_exists('apache_get_modules')) {
            $modules = apache_get_modules();
            $required = ['mod_rewrite', 'mod_php'];
            echo "<ul>";
            foreach ($required as $req) {
                $enabled = in_array($req, $modules);
                $status = $enabled ? '<span class="success">✓ Enabled</span>' : '<span class="error">✗ Disabled</span>';
                echo "<li><strong>$req</strong>: $status</li>";
            }
            echo "</ul>";
        } else {
            echo "<p class="info">Cannot check Apache modules (function not available)</p>";
        }
        ?>
    </div>

    <div class="box">
        <h2>💾 Database Connection Test</h2>
        <?php
        if (file_exists('config/db.php')) {
            require_once 'config/db.php';
            try {
                $conn = getDBConnection();
                if ($conn) {
                    echo '<p class="success">✓ Database connection successful!</p>';
                    echo '<p><strong>Database Name:</strong> ' . DB_NAME . '</p>';
                    closeDBConnection($conn);
                } else {
                    echo '<p class="error">✗ Database connection failed</p>';
                }
            } catch (Exception $e) {
                echo '<p class="error">✗ Database error: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
        } else {
            echo '<p class="error">✗ Database config file not found</p>';
        }
        ?>
    </div>

    <div class="box">
        <h2>🚀 Quick Actions</h2>
        <a href="index.php" class="btn">Go to Homepage</a>
        <a href="login.php" class="btn">Go to Login</a>
        <a href="setup_database.php" class="btn">Setup Database</a>
        <a href="test.php" class="btn">Test Page</a>
    </div>

    <div class="box">
        <h2>❓ Troubleshooting Steps</h2>
        <ol>
            <li><strong>Check URL:</strong> Make sure you're using <code>http://localhost/Final_Project_webTech_Peter/</code></li>
            <li><strong>Check Apache:</strong> Ensure Apache is running in XAMPP Control Panel</li>
            <li><strong>Check Port:</strong> If port 80 is busy, try <code>http://localhost:8080/Final_Project_webTech_Peter/</code></li>
            <li><strong>Restart Apache:</strong> Stop and start Apache in XAMPP Control Panel</li>
            <li><strong>Check Error Log:</strong> Look at <code>C:\xampp\apache\logs\error.log</code></li>
        </ol>
    </div>
</body>
</html>

