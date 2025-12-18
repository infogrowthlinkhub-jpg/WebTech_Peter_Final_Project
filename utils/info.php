<?php
/**
 * Server Information Diagnostic Page
 * This will help identify the issue
 */
echo "<h1>Server Information</h1>";
echo "<h2>PHP Information:</h2>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
echo "<p><strong>Current Directory:</strong> " . __DIR__ . "</p>";
echo "<p><strong>Script Name:</strong> " . $_SERVER['SCRIPT_NAME'] . "</p>";
echo "<p><strong>Request URI:</strong> " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "</p>";
echo "<p><strong>Document Root:</strong> " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "</p>";

echo "<h2>File Check:</h2>";
$files = ['../index.php', '../utils/test.php', '../pages/login.php', '../config/db.php'];
foreach ($files as $file) {
    $exists = file_exists($file);
    $color = $exists ? 'green' : 'red';
    echo "<p style='color: $color;'><strong>$file:</strong> " . ($exists ? 'EXISTS ✓' : 'NOT FOUND ✗') . "</p>";
}

echo "<h2>Apache Modules:</h2>";
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    echo "<ul>";
    foreach ($modules as $module) {
        echo "<li>$module</li>";
    }
    echo "</ul>";
} else {
    echo "<p>Cannot retrieve Apache modules (function not available)</p>";
}

echo "<h2>Links:</h2>";
echo "<p><a href='../index.php'>Go to Index Page</a></p>";
echo "<p><a href='test.php'>Go to Test Page</a></p>";
?>

