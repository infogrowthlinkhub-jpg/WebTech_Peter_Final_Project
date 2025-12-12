<?php
/**
 * NileTech Learning Website - Unified Database Connection
 * Auto-detects local vs live server and loads correct credentials.
 */

// -----------------------------------------------------
// Detect Environment (local or live server)
// -----------------------------------------------------
function detectEnvironment() {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Local environments
    if (strpos($host, 'localhost') !== false || 
        strpos($host, '127.0.0.1') !== false) 
    {
        return 'local';
    }

    // Otherwise it's the live server
    return 'server';
}

// -----------------------------------------------------
// Database credentials (local + live server)
// -----------------------------------------------------
function getDBConfig() {
    $env = detectEnvironment();

    // LOCAL (XAMPP)
    if ($env === 'local') {
        return [
            'host' => 'localhost',
            'user' => 'root',
            'pass' => '',
            'name' => 'webtech_2025A_peter_mayen'  // LOCAL DB (must match database.sql)
        ];
    }

    // LIVE SERVER
    return [
        'host' => 'localhost',
        'user' => 'peter.mayen',
        'pass' => 'Machuek',
        'name' => 'webtech_2025A_peter_mayen'   // LIVE DB (must match database.sql)
    ];
}

// Load correct DB credentials
$db = getDBConfig();

define('DB_HOST', $db['host']);
define('DB_USER', $db['user']);
define('DB_PASS', $db['pass']);
define('DB_NAME', $db['name']);

// -----------------------------------------------------
// Create Database Connection
// -----------------------------------------------------
/**
 * Create database connection
 * 
 * @return mysqli|false Returns mysqli connection object on success, false on failure
 */
function getDBConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if ($conn->connect_error) {
            // Log error
            error_log("Database connection failed: " . $conn->connect_error);
            
            // In production, show user-friendly message
            if (php_sapi_name() !== 'cli') {
                // Check if we're in an API context
                if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
                    http_response_code(500);
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Database connection failed. Please try again later.']);
                    exit;
                }
                // For regular pages, show user-friendly error page
                ?>
                <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Database Connection Error</title>
                    <style>
                        body { font-family: Arial, sans-serif; max-width: 600px; margin: 100px auto; padding: 20px; text-align: center; background: #f5f5f5; }
                        .error-box { background: #fee2e2; border: 2px solid #ef4444; border-radius: 10px; padding: 30px; }
                        h1 { color: #dc2626; }
                        .btn { display: inline-block; margin-top: 20px; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; }
                        .btn:hover { background: #5568d3; }
                        code { background: #f3f4f6; padding: 2px 6px; border-radius: 3px; }
                    </style>
                </head>
                <body>
                    <div class="error-box">
                        <h1>⚠️ Database Connection Failed</h1>
                        <p>The application cannot connect to the database.</p>
                        <p><strong>Please check:</strong></p>
                        <ul style="text-align: left; display: inline-block;">
                            <li>MySQL is running</li>
                            <li>Database <code><?php echo DB_NAME; ?></code> exists</li>
                            <li>Database credentials are correct in <code>config/db.php</code></li>
                        </ul>
                        <a href="setup_database.php" class="btn">Setup & Verify Database</a>
                    </div>
                </body>
                </html>
                <?php
                exit;
            } else {
                die("Connection failed: " . $conn->connect_error);
            }
        }

        // Ensure correct encoding
        $conn->set_charset("utf8mb4");
        
        // Enable error reporting for development (disable in production)
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        }

        return $conn;

    } catch (Exception $e) {
        error_log("Exception DB Error: " . $e->getMessage());
        if (php_sapi_name() !== 'cli') {
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Database Error</title>
                <style>
                    body { font-family: Arial, sans-serif; max-width: 600px; margin: 100px auto; padding: 20px; text-align: center; background: #f5f5f5; }
                    .error-box { background: #fee2e2; border: 2px solid #ef4444; border-radius: 10px; padding: 30px; }
                    h1 { color: #dc2626; }
                </style>
            </head>
            <body>
                <div class="error-box">
                    <h1>❌ Database Error</h1>
                    <p>An error occurred while connecting to the database.</p>
                    <p>Please contact the administrator.</p>
                </div>
            </body>
            </html>
            <?php
            exit;
        } else {
            die("Database error: " . $e->getMessage());
        }
    }
}

// -----------------------------------------------------
// Close connection helper
// -----------------------------------------------------
function closeDBConnection($conn) {
    if ($conn) {
        $conn->close();
    }
}

?>
