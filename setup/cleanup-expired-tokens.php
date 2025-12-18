<?php
/**
 * Cleanup Expired Password Reset Tokens
 * 
 * This script deletes expired password reset tokens from the database.
 * It can be run manually or via cron job.
 * 
 * Recommended cron schedule: Run hourly
 * Example cron entry: 0 * * * * /usr/bin/php /path/to/cleanup-expired-tokens.php
 * 
 * For Windows Task Scheduler, create a task that runs:
 * php.exe "C:\xampp\htdocs\Final_Project_webTech_Peter\cleanup-expired-tokens.php"
 */

require_once __DIR__ . '/config/db.php';

// Get database connection
$conn = getDBConnection();

if (!$conn) {
    error_log("Cleanup: Database connection failed - " . mysqli_connect_error());
    exit(1);
}

// Delete expired tokens (tokens where expires_at < NOW())
$deleteQuery = "DELETE FROM password_reset_tokens WHERE expires_at < NOW()";
$result = $conn->query($deleteQuery);

if ($result) {
    $deletedCount = $conn->affected_rows;
    error_log("Cleanup: Deleted {$deletedCount} expired password reset token(s)");
    echo "Successfully deleted {$deletedCount} expired token(s).\n";
} else {
    error_log("Cleanup: Failed to delete expired tokens - " . $conn->error);
    echo "Error: Failed to delete expired tokens.\n";
    closeDBConnection($conn);
    exit(1);
}

closeDBConnection($conn);
exit(0);

