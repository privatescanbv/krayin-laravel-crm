<?php

$testFile = 'tests/Feature/PersonLeadSyncTest.php';
$content = file_get_contents($testFile);

// Remove all 'title' => 'Test Lead', lines
$content = preg_replace("/\s*'title'\s*=>\s*'[^']*',?\n/", "\n", $content);

// Clean up any double newlines
$content = preg_replace("/\n\n+/", "\n\n", $content);

file_put_contents($testFile, $content);

echo "Fixed all title references in $testFile\n";