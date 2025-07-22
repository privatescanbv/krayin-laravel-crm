<?php

$file = 'tests/Feature/PersonLeadSyncTest.php';
$content = file_get_contents($file);

// List of tests that need authentication fixes (all except the ones already fixed)
$testsToFix = [
    'shows field differences between person and lead',
    'can update person with lead data',
    'can update lead data during sync',
    'handles array fields correctly during sync',
    'shows no differences message when records are identical',
    'validates required route parameters',
    'handles empty form submission gracefully',
];

foreach ($testsToFix as $testName) {
    // Find the test function
    $pattern = "/(test\('$testName', function \(\) \{)/";

    $content = preg_replace_callback($pattern, function ($matches) {
        return $matches[1]."\n    \$user = ensureAuthenticated();";
    }, $content);
}

// Update all Person::factory()->create calls to include user_id if not present
$content = preg_replace_callback(
    '/(Person::factory\(\)->create\(\[)(.*?)(\]\);)/s',
    function ($matches) {
        $prefix = $matches[1];
        $body = $matches[2];
        $suffix = $matches[3];

        // Skip if user_id already present
        if (strpos($body, 'user_id') !== false) {
            return $matches[0];
        }

        // Add user_id
        if (trim($body) === '') {
            $body = "\n        'user_id' => \$user->id,\n    ";
        } else {
            $body = $body.",\n        'user_id' => \$user->id,";
        }

        return $prefix.$body.$suffix;
    },
    $content
);

// Update all Lead::factory()->create calls to include user_id if not present
$content = preg_replace_callback(
    '/(Lead::factory\(\)->create\(\[)(.*?)(\]\);)/s',
    function ($matches) {
        $prefix = $matches[1];
        $body = $matches[2];
        $suffix = $matches[3];

        // Skip if user_id already present
        if (strpos($body, 'user_id') !== false) {
            return $matches[0];
        }

        // Add user_id
        if (trim($body) === '') {
            $body = "\n        'user_id' => \$user->id,\n    ";
        } else {
            $body = $body.",\n        'user_id' => \$user->id,";
        }

        return $prefix.$body.$suffix;
    },
    $content
);

// Remove any duplicate ensureAuthenticated() calls and re-authentication lines
$content = preg_replace('/\$user = ensureAuthenticated\(\);\s*\n\s*\$user = ensureAuthenticated\(\);/', '$user = ensureAuthenticated();', $content);
$content = preg_replace('/\/\/ Re-authenticate to prevent 302 redirect\s*\n\s*\$this->actingAs\(\$this->user, \'user\'\);\s*\n/', '', $content);

file_put_contents($file, $content);
echo "Fixed all authentication issues in PersonLeadSyncTest.php\n";
