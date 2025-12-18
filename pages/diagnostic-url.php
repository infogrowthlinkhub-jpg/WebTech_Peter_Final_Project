<?php
/**
 * URL Diagnostic Tool - Helps diagnose "Not Found" errors
 * Access this file to check your Apache/PHP configuration
 */

// Get server information
$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? 'N/A';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? 'N/A';
$requestUri = $_SERVER['REQUEST_URI'] ?? 'N/A';
$serverName = $_SERVER['SERVER_NAME'] ?? 'N/A';
$serverPort = $_SERVER['SERVER_PORT'] ?? 'N/A';
$httpHost = $_SERVER['HTTP_HOST'] ?? 'N/A';
$scriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? 'N/A';

// Calculate project path
$projectPath = str_replace($documentRoot, '', dirname(__FILE__));
$projectPath = str_replace('\\', '/', $projectPath);
$projectPath = trim($projectPath, '/');

// Build correct URLs
$baseUrl = "http://{$httpHost}/{$projectPath}";
$indexUrl = "{$baseUrl}/index.php";
$testUrl = "{$baseUrl}/test.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>URL Diagnostic Tool - Fix "Not Found" Error</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 2em;
            margin-bottom: 10px;
        }
        .content {
            padding: 30px;
        }
        .section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }
        .section h2 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.5em;
        }
        .info-box {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border: 1px solid #e0e0e0;
        }
        .info-box strong {
            color: #667eea;
            display: block;
            margin-bottom: 5px;
        }
        .info-box code {
            background: #f0f0f0;
            padding: 2px 8px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            color: #d63384;
            word-break: break-all;
        }
        .url-box {
            background: #e7f3ff;
            border: 2px solid #2196F3;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }
        .url-box a {
            color: #1976D2;
            text-decoration: none;
            font-weight: bold;
            font-size: 1.1em;
            display: block;
            padding: 10px;
            background: white;
            border-radius: 5px;
            margin: 5px 0;
            transition: all 0.3s;
        }
        .url-box a:hover {
            background: #2196F3;
            color: white;
            transform: translateX(5px);
        }
        .status {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            margin: 5px 0;
        }
        .status.success {
            background: #4caf50;
            color: white;
        }
        .status.warning {
            background: #ff9800;
            color: white;
        }
        .status.error {
            background: #f44336;
            color: white;
        }
        .checklist {
            list-style: none;
            padding: 0;
        }
        .checklist li {
            padding: 10px;
            margin: 5px 0;
            background: white;
            border-radius: 5px;
            border-left: 4px solid #4caf50;
        }
        .checklist li::before {
            content: "✓ ";
            color: #4caf50;
            font-weight: bold;
            margin-right: 10px;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .alert.warning {
            background: #fff3cd;
            border-left: 4px solid #ff9800;
            color: #856404;
        }
        .alert.success {
            background: #d4edda;
            border-left: 4px solid #4caf50;
            color: #155724;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 URL Diagnostic Tool</h1>
            <p>Fix "Not Found" Errors - Step by Step Guide</p>
        </div>
        
        <div class="content">
            <!-- Current Server Information -->
            <div class="section">
                <h2>📊 Current Server Information</h2>
                <div class="info-box">
                    <strong>Document Root:</strong>
                    <code><?php echo htmlspecialchars($documentRoot); ?></code>
                </div>
                <div class="info-box">
                    <strong>Project Path:</strong>
                    <code><?php echo htmlspecialchars($projectPath ?: '/'); ?></code>
                </div>
                <div class="info-box">
                    <strong>HTTP Host:</strong>
                    <code><?php echo htmlspecialchars($httpHost); ?></code>
                </div>
                <div class="info-box">
                    <strong>Server Port:</strong>
                    <code><?php echo htmlspecialchars($serverPort); ?></code>
                </div>
                <div class="info-box">
                    <strong>Current Script:</strong>
                    <code><?php echo htmlspecialchars($scriptName); ?></code>
                </div>
            </div>

            <!-- Correct URLs -->
            <div class="section">
                <h2>✅ Use These EXACT URLs</h2>
                <div class="url-box">
                    <strong style="display: block; margin-bottom: 10px; color: #1976D2;">Homepage:</strong>
                    <a href="<?php echo htmlspecialchars($indexUrl); ?>" target="_blank">
                        <?php echo htmlspecialchars($indexUrl); ?>
                    </a>
                    <a href="<?php echo htmlspecialchars($baseUrl); ?>/" target="_blank">
                        <?php echo htmlspecialchars($baseUrl); ?>/
                    </a>
                </div>
                <div class="url-box">
                    <strong style="display: block; margin-bottom: 10px; color: #1976D2;">Test PHP:</strong>
                    <a href="<?php echo htmlspecialchars($testUrl); ?>" target="_blank">
                        <?php echo htmlspecialchars($testUrl); ?>
                    </a>
                </div>
            </div>

            <!-- File Check -->
            <div class="section">
                <h2>📁 File Existence Check</h2>
                <?php
                $filesToCheck = [
                    'index.php' => 'Homepage',
                    'test.php' => 'PHP Test File',
                    'login.php' => 'Login Page',
                    'config/db.php' => 'Database Config',
                    '.htaccess' => 'Apache Config'
                ];
                
                $allExist = true;
                foreach ($filesToCheck as $file => $description) {
                    $exists = file_exists(__DIR__ . '/' . $file);
                    $allExist = $allExist && $exists;
                    $statusClass = $exists ? 'success' : 'error';
                    $statusText = $exists ? '✓ Exists' : '✗ Missing';
                    echo "<div class='info-box'>";
                    echo "<strong>{$description}:</strong> ";
                    echo "<span class='status {$statusClass}'>{$statusText}</span> ";
                    echo "<code>{$file}</code>";
                    echo "</div>";
                }
                
                if ($allExist) {
                    echo "<div class='alert success'>✅ All essential files exist!</div>";
                } else {
                    echo "<div class='alert warning'>⚠️ Some files are missing. Check your project structure.</div>";
                }
                ?>
            </div>

            <!-- PHP Configuration -->
            <div class="section">
                <h2>🐘 PHP Configuration</h2>
                <div class="info-box">
                    <strong>PHP Version:</strong>
                    <span class="status success"><?php echo PHP_VERSION; ?></span>
                </div>
                <div class="info-box">
                    <strong>Server API:</strong>
                    <code><?php echo php_sapi_name(); ?></code>
                </div>
                <div class="info-box">
                    <strong>Document Root Exists:</strong>
                    <?php 
                    $docRootExists = is_dir($documentRoot);
                    $statusClass = $docRootExists ? 'success' : 'error';
                    $statusText = $docRootExists ? '✓ Yes' : '✗ No';
                    echo "<span class='status {$statusClass}'>{$statusText}</span>";
                    ?>
                </div>
            </div>

            <!-- Quick Fix Checklist -->
            <div class="section">
                <h2>🚀 Quick Fix Checklist</h2>
                <ul class="checklist">
                    <li>Apache is running in XAMPP Control Panel</li>
                    <li>Using correct URL: <code><?php echo htmlspecialchars($baseUrl); ?>/</code></li>
                    <li>Folder exists at: <code><?php echo htmlspecialchars($documentRoot . '/' . $projectPath); ?></code></li>
                    <li>test.php works (shows "PHP IS WORKING!")</li>
                    <li>Tried restarting Apache</li>
                </ul>
            </div>

            <!-- Common Issues -->
            <div class="section">
                <h2>🔍 Common Issues & Solutions</h2>
                <div class="alert warning">
                    <strong>Issue 1: Wrong URL Format</strong><br>
                    ❌ WRONG: <code>http://localhost/final_project_webtech_peter/</code> (wrong case)<br>
                    ✅ CORRECT: <code><?php echo htmlspecialchars($baseUrl); ?>/</code>
                </div>
                <div class="alert warning">
                    <strong>Issue 2: Apache Not Running</strong><br>
                    1. Open XAMPP Control Panel<br>
                    2. Check if Apache shows "Running" (green)<br>
                    3. If not, click "Start" next to Apache<br>
                    4. Wait 10 seconds for Apache to start
                </div>
                <div class="alert warning">
                    <strong>Issue 3: Port Mismatch</strong><br>
                    If Apache is using port 8080 instead of 80, use:<br>
                    <code>http://localhost:8080<?php echo htmlspecialchars('/' . $projectPath); ?>/</code>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="section" style="text-align: center;">
                <h2>🎯 Next Steps</h2>
                <a href="<?php echo htmlspecialchars($testUrl); ?>" class="btn" target="_blank">Test PHP</a>
                <a href="<?php echo htmlspecialchars($indexUrl); ?>" class="btn" target="_blank">Go to Homepage</a>
                <a href="../utils/check.php" class="btn" target="_blank">Full System Check</a>
            </div>
        </div>
    </div>
</body>
</html>

