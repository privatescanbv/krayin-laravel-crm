<?php

// Quick test script to check if email attachments functionality works
// Run with: php test_email_attachments.php

require_once 'vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = new Application(realpath(__DIR__));
$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);
$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);
$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Testing email attachments functionality...\n\n";

// Test if SugarCRM connection works
try {
    $connection = 'sugarcrm';
    DB::connection($connection)->getPdo();
    echo "✓ SugarCRM database connection successful\n";
    
    // Check if required tables exist
    $requiredTables = ['emails', 'emails_beans', 'notes'];
    foreach ($requiredTables as $table) {
        if (DB::connection($connection)->getSchemaBuilder()->hasTable($table)) {
            echo "✓ Table '{$table}' exists\n";
        } else {
            echo "✗ Table '{$table}' does not exist\n";
        }
    }
    
    // Check if there are any emails linked to leads
    $emailCount = DB::connection($connection)
        ->table('emails_beans')
        ->where('bean_module', 'Leads')
        ->where('deleted', 0)
        ->count();
    echo "\n📧 Found {$emailCount} emails linked to leads\n";
    
    // Check if there are any notes (attachments) linked to emails
    $attachmentCount = DB::connection($connection)
        ->table('notes')
        ->where('parent_type', 'Emails')
        ->where('deleted', 0)
        ->whereNotNull('filename')
        ->count();
    echo "📎 Found {$attachmentCount} email attachments (notes with filenames)\n";
    
    // Show sample data
    if ($attachmentCount > 0) {
        echo "\nSample attachments:\n";
        $sampleAttachments = DB::connection($connection)
            ->table('notes')
            ->where('parent_type', 'Emails')
            ->where('deleted', 0)
            ->whereNotNull('filename')
            ->limit(5)
            ->get(['id', 'filename', 'file_mime_type', 'parent_id']);
            
        foreach ($sampleAttachments as $att) {
            echo "  - {$att->filename} ({$att->file_mime_type}) -> email {$att->parent_id}\n";
        }
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\nTest completed.\n";