<?php
// Test file to verify Apache is working
echo "PHP is working!<br>";
echo "Current directory: " . __DIR__ . "<br>";
echo "Document root: " . $_SERVER['DOCUMENT_ROOT'] ?? 'not set' . "<br>";
echo "Request URI: " . $_SERVER['REQUEST_URI'] ?? 'not set' . "<br>";

// Check if CodeIgniter files exist
$paths = [
    '../app/Config/Paths.php',
    '../system/bootstrap.php',
    '../vendor/autoload.php'
];

foreach ($paths as $path) {
    echo "Checking $path: " . (file_exists($path) ? "EXISTS" : "MISSING") . "<br>";
}

// Show files in current directory
echo "<br>Files in current directory:<br>";
foreach (scandir(__DIR__) as $file) {
    if ($file !== '.' && $file !== '..') {
        echo "- $file<br>";
    }
}
?>
