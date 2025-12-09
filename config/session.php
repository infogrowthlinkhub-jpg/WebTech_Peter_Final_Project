<?php
/**
 * Session bootstrap and access control for NileTech Learning Website.
 *
 * - Starts a secure PHP session
 * - Enforces login requirement (expects `$_SESSION['user_id']` to be set on login)
 * - Implements optional inactivity timeout (default: 30 minutes)
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// -----------------------------
// Inactivity timeout (30 mins)
// -----------------------------
$timeoutDuration = 30 * 60; // 30 minutes in seconds

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeoutDuration) {
    // Session has expired due to inactivity
    session_unset();
    session_destroy();

    header('Location: login.php?timeout=1');
    exit();
}

// Update activity timestamp
$_SESSION['LAST_ACTIVITY'] = time();

// -----------------------------
// Access control
// -----------------------------
// Require a logged-in user; adjust the key name to match your login logic.
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Optional convenience variables for templates
$currentUserId   = $_SESSION['user_id'];
$currentUserName = $_SESSION['user_name'] ?? 'Learner';


