<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Step 1 - PHP works ✓</h2>";

// Try every possible config path
$paths = [
    '../includes/config.php',
    '../../includes/config.php',
    $_SERVER['DOCUMENT_ROOT'] . '/school-project/includes/config.php',
    $_SERVER['DOCUMENT_ROOT'] . '/includes/config.php',
    '../config.php',
    'config.php',
];

echo "<h3>Searching for config.php...</h3><ul>";
foreach ($paths as $p) {
    $exists = file_exists($p);
    $color = $exists ? 'green' : 'red';
    echo "<li style='color:$color'>$p — " . ($exists ? '✓ FOUND' : '✗ not found') . "</li>";
}
echo "</ul>";

// Show actual directory structure
echo "<h3>Your folder structure:</h3><pre>";
echo "DOCUMENT_ROOT: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Current file : " . __FILE__ . "\n";
echo "Current dir  : " . __DIR__ . "\n";

// List parent directory
$parent = dirname(__DIR__);
echo "\nContents of $parent:\n";
foreach (scandir($parent) as $f) {
    if ($f === '.' || $f === '..') continue;
    echo "  " . (is_dir("$parent/$f") ? '[DIR] ' : '      ') . $f . "\n";
}
echo "</pre>";