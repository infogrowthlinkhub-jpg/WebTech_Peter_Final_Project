<?php
// Ultra-simple test - if this works, everything else should work
echo "✅ PHP IS WORKING!<br>";
echo "Current file: " . __FILE__ . "<br>";
echo "Document root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "<br>";
echo "Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "<br>";
echo "<br><a href='../index.php'>Click here to go to homepage</a>";
?>
