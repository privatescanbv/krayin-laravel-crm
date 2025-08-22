<?php

// Simple test to debug the persons relationship
// Run this with: php artisan tinker < test_persons_relation.php

use Webkul\Lead\Models\Lead;
use Webkul\Contact\Models\Person;

echo "Testing Lead-Persons Relationship\n";
echo "==================================\n\n";

// Test 1: Check if pivot table exists
try {
    $pivotCount = DB::table('lead_persons')->count();
    echo "✅ Pivot table 'lead_persons' exists with {$pivotCount} records\n";
} catch (Exception $e) {
    echo "❌ Pivot table 'lead_persons' does not exist: " . $e->getMessage() . "\n";
    exit;
}

// Test 2: Try to load a lead with persons
try {
    $lead = Lead::with('persons')->first();
    if ($lead) {
        echo "✅ Lead loaded: {$lead->title}\n";
        echo "✅ Persons count: " . $lead->persons->count() . "\n";
        
        if ($lead->persons->count() > 0) {
            echo "✅ First person: " . $lead->persons->first()->name . "\n";
        }
    } else {
        echo "❌ No leads found in database\n";
    }
} catch (Exception $e) {
    echo "❌ Error loading lead with persons: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

// Test 3: Check pivot table structure
try {
    $columns = DB::select("DESCRIBE lead_persons");
    echo "\n✅ Pivot table structure:\n";
    foreach ($columns as $column) {
        echo "  - {$column->Field}: {$column->Type}\n";
    }
} catch (Exception $e) {
    echo "❌ Could not describe pivot table: " . $e->getMessage() . "\n";
}

echo "\nTest completed.\n";