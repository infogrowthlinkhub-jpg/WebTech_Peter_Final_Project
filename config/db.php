<?php
/**
 * NileTech Learning Website - Database Connection
 * 
 * This file handles the MySQL database connection using mysqli.
 * Update the credentials according to your database configuration.
 */

// Database configuration
define('DB_HOST', 'localhost');        // Database host (usually 'localhost')
define('DB_USER', 'root');              // Database username
define('DB_PASS', '');                // Database password (empty for XAMPP default)
define('DB_NAME', 'webtech_2025A_peter_mayen');     // Database name

/**
 * Create database connection
 * 
 * @return mysqli|false Returns mysqli connection object on success, false on failure
 */
function getDBConnection() {
    // Create connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
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
                    body { font-family: Arial, sans-serif; max-width: 600px; margin: 100px auto; padding: 20px; text-align: center; }
                    .error-box { background: #fee2e2; border: 2px solid #ef4444; border-radius: 10px; padding: 30px; }
                    h1 { color: #dc2626; }
                    .btn { display: inline-block; margin-top: 20px; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; }
                    .btn:hover { background: #5568d3; }
                </style>
            </head>
            <body>
                <div class="error-box">
                    <h1>⚠️ Database Connection Failed</h1>
                    <p>The application cannot connect to the database.</p>
                    <p><strong>Please check:</strong></p>
                    <ul style="text-align: left; display: inline-block;">
                        <li>XAMPP MySQL is running</li>
                        <li>Database <code><?php echo DB_NAME; ?></code> exists</li>
                        <li>Database credentials are correct</li>
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
    
    // Set charset to utf8mb4 for proper character encoding
    $conn->set_charset("utf8mb4");
    
    // Enable error reporting for development (disable in production)
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    }
    
    return $conn;
}

/**
 * Close database connection
 * 
 * @param mysqli $conn Database connection object
 */
function closeDBConnection($conn) {
    if ($conn) {
        $conn->close();
    }
}

?>
