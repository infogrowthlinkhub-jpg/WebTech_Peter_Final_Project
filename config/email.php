<?php
/**
 * NileTech Learning Website - Email Configuration
 * 
 * Configure email settings for sending notifications
 * For local development, you may need to configure SMTP settings
 * For production, update these settings according to your server
 */

// Email Configuration
define('EMAIL_ENABLED', true); // Set to false to disable all email sending (for testing)

// Default sender information
define('EMAIL_FROM_ADDRESS', 'noreply@niletechlearning.com');
define('EMAIL_FROM_NAME', 'NileTech Learning Platform');

// Feedback notification recipient
define('FEEDBACK_EMAIL', 'africantransformative@gmail.com');

// SMTP Configuration (if using SMTP instead of mail())
define('SMTP_ENABLED', false); // Set to true to use SMTP instead of mail()
define('SMTP_HOST', 'smtp.gmail.com'); // SMTP server address
define('SMTP_PORT', 587); // SMTP port (587 for TLS, 465 for SSL)
define('SMTP_SECURE', 'tls'); // 'tls' or 'ssl'
define('SMTP_USERNAME', ''); // SMTP username (email address)
define('SMTP_PASSWORD', ''); // SMTP password or app password
define('SMTP_AUTH', true); // Enable SMTP authentication

/**
 * Check if email is enabled
 * @return bool True if email is enabled
 */
function isEmailEnabled() {
    return defined('EMAIL_ENABLED') && EMAIL_ENABLED === true;
}

/**
 * Get feedback email recipient
 * @return string Feedback email address
 */
function getFeedbackEmail() {
    return defined('FEEDBACK_EMAIL') ? FEEDBACK_EMAIL : 'africantransformative@gmail.com';
}

/**
 * Get default sender email
 * @return string Sender email address
 */
function getDefaultFromEmail() {
    return defined('EMAIL_FROM_ADDRESS') ? EMAIL_FROM_ADDRESS : 'noreply@niletechlearning.com';
}

/**
 * Get default sender name
 * @return string Sender name
 */
function getDefaultFromName() {
    return defined('EMAIL_FROM_NAME') ? EMAIL_FROM_NAME : 'NileTech Learning Platform';
}

?>
