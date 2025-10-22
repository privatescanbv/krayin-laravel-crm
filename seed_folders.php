<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Webkul\Email\Models\Folder;

// Create default folders
$folders = [
    'Inbox',
    'Imported', 
    'Sent',
    'Draft',
    'Trash'
];

foreach ($folders as $folderName) {
    $folder = Folder::firstOrCreate(['name' => $folderName]);
    echo "Created folder: {$folder->name}\n";
}

// Create subfolders
$inbox = Folder::where('name', 'Inbox')->first();
if ($inbox) {
    $subfolders = ['Important', 'Archive'];
    foreach ($subfolders as $subfolderName) {
        $subfolder = Folder::firstOrCreate([
            'name' => $subfolderName,
            'parent_id' => $inbox->id
        ]);
        echo "Created subfolder: {$subfolder->name} under {$inbox->name}\n";
    }
}

echo "Folder seeding completed!\n";