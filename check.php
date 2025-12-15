<?php
/**
 * Quick Check Script - Verifies everything is working
 * Access: http://localhost/Final_Project_webTech_Peter/check.php
 */
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>System Check - NileTech</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 900px; margin: 30px auto; padding: 20px; background: #f0f0f0; }
        .success { color: #10b981; font-weight: bold; }
        .error { color: #ef4444; font-weight: bold; }
        .warning { color: #f59e0b; font-weight: bold; }
        .box { background: white; padding: 20px; margin: 15px 0; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h1 { color: #006994; }
        h2 { color: #333; border-bottom: 2px solid #006994; padding-bottom: 8px; }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 3px; }
        ul { line-height: 1.8; }
        .btn { display: inline-block; padding: 12px 24px; background: #006994; color: white; text-decoration: none; border-radius: 5px; margin: 5px; }
        .btn:hover { background: #005577; }
    </style>
</head>
<body>
    <div class="box">
        <h1>✅ System Check - NileTech Learning Platform</h1>
        <p>If you can see this page, <strong class="success">PHP and Apache are working!</strong></p>
    </div>

    <div class="box">
        <h2>📋 Server Information</h2>
        <ul>
            <li><strong>PHP Version:</strong> <?php echo phpversion(); ?></li>
            <li><strong>Server:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?></li>
            <li><strong>Document Root:</strong> <code><?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'N/A'; ?></code></li>
            <li><strong>Current File:</strong> <code><?php echo __FILE__; ?></code></li>
            <li><strong>Request URI:</strong> <code><?php echo $_SERVER['REQUEST_URI'] ?? 'N/A'; ?></code></li>
            <li><strong>Script Name:</strong> <code><?php echo $_SERVER['SCRIPT_NAME'] ?? 'N/A'; ?></code></li>
        </ul>
    </div>

    <div class="box">
        <h2>📁 Critical Files Check</h2>
        <ul>
            <?php
            $criticalFiles = [
                'index.php' => 'Homepage',
                'login.php' => 'Login page',
                'signup.php' => 'Signup page',
                'config/db.php' => 'Database config',
                'config/session.php' => 'Session config',
                'includes/functions.php' => 'Functions library',
                'css/style.css' => 'Stylesheet',
                '.htaccess' => 'Apache config'
            ];
            
            $allExist = true;
            foreach ($criticalFiles as $file => $desc) {
                $exists = file_exists($file);
                $allExist = $allExist && $exists;
                $status = $exists ? '<span class="success">✓ EXISTS</span>' : '<span class="error">✗ MISSING</span>';
                echo "<li><strong>$file</strong> ($desc): $status</li>";
            }
            
            if ($allExist) {
                echo '<li class="success"><strong>All critical files are present!</strong></li>';
            } else {
                echo '<li class="error"><strong>Some files are missing! Please check the list above.</strong></li>';
            }
            ?>
        </ul>
    </div>

    <div class="box">
        <h2>💾 Database Connection Test</h2>
        <?php
        if (file_exists('config/db.php')) {
            try {
                require_once 'config/db.php';
                $conn = getDBConnection();
                if ($conn) {
                    echo '<p class="success">✓ Database connection successful!</p>';
                    echo '<p><strong>Database:</strong> ' . DB_NAME . '</p>';
                    echo '<p><strong>Host:</strong> ' . DB_HOST . '</p>';
                    echo '<p><strong>User:</strong> ' . DB_USER . '</p>';
                    closeDBConnection($conn);
                } else {
                    echo '<p class="error">✗ Database connection failed</p>';
                    echo '<p>Run <a href="setup_database.php">setup_database.php</a> to configure the database.</p>';
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
        <h2>🌐 Correct URLs to Access Your Site</h2>
        <p><strong>Use these exact URLs:</strong></p>
        <ul>
            <li><strong>Homepage:</strong> <a href="index.php" target="_blank">http://localhost/Final_Project_webTech_Peter/index.php</a></li>
            <li><strong>Homepage (short):</strong> <a href="/Final_Project_webTech_Peter/" target="_blank">http://localhost/Final_Project_webTech_Peter/</a></li>
            <li><strong>Login:</strong> <a href="login.php" target="_blank">http://localhost/Final_Project_webTech_Peter/login.php</a></li>
            <li><strong>Signup:</strong> <a href="signup.php" target="_blank">http://localhost/Final_Project_webTech_Peter/signup.php</a></li>
            <li><strong>Diagnostic:</strong> <a href="diagnostic.php" target="_blank">http://localhost/Final_Project_webTech_Peter/diagnostic.php</a></li>
        </ul>
        <p class="warning"><strong>⚠ Important:</strong> Make sure the folder name is exactly: <code>Final_Project_webTech_Peter</code></p>
    </div>

    <div class="box">
        <h2>🔧 Apache Configuration</h2>
        <?php
        if (function_exists('apache_get_modules')) {
            $modules = apache_get_modules();
            $required = ['mod_rewrite', 'mod_php'];
            echo '<ul>';
            foreach ($required as $req) {
                $enabled = in_array($req, $modules);
                $status = $enabled ? '<span class="success">✓ Enabled</span>' : '<span class="warning">⚠ Not Enabled (may not be critical)</span>';
                echo "<li><strong>$req</strong>: $status</li>";
            }
            echo '</ul>';
        } else {
            echo '<p class="info">Cannot check Apache modules (function not available - this is normal)</p>';
        }
        ?>
    </div>

    <div class="box">
        <h2>🚀 Quick Navigation</h2>
        <a href="index.php" class="btn">Go to Homepage</a>
        <a href="login.php" class="btn">Go to Login</a>
        <a href="signup.php" class="btn">Go to Signup</a>
        <a href="setup_database.php" class="btn">Setup Database</a>
        <a href="diagnostic.php" class="btn">Full Diagnostic</a>
    </div>

    <div class="box">
        <h2>❓ Troubleshooting</h2>
        <p><strong>If you're still getting "Not Found" errors:</strong></p>
        <ol>
            <li><strong>Check the URL:</strong> Use <code>http://localhost/Final_Project_webTech_Peter/</code> (exact folder name)</li>
            <li><strong>Restart Apache:</strong> Stop and start Apache in XAMPP Control Panel</li>
            <li><strong>Check Apache Error Log:</strong> <code>C:\xampp\apache\logs\error.log</code></li>
            <li><strong>Try different port:</strong> If port 80 is busy, use <code>http://localhost:8080/Final_Project_webTech_Peter/</code></li>
            <li><strong>Verify folder location:</strong> Should be in <code>C:\xampp\htdocs\Final_Project_webTech_Peter\</code></li>
        </ol>
    </div>
</body>
</html>

