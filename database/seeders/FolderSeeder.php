<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Webkul\Email\Models\Folder;

class FolderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Create root folders
        $inbox = Folder::create([
            'name' => 'Inbox',
            'parent_id' => null,
        ]);

        $imported = Folder::create([
            'name' => 'Imported',
            'parent_id' => null,
        ]);

        // Create some subfolders for better organization
        Folder::create([
            'name' => 'Sent',
            'parent_id' => null,
        ]);

        Folder::create([
            'name' => 'Draft',
            'parent_id' => null,
        ]);

        Folder::create([
            'name' => 'Trash',
            'parent_id' => null,
        ]);

        // Create subfolders under Inbox
        Folder::create([
            'name' => 'Important',
            'parent_id' => $inbox->id,
        ]);

        Folder::create([
            'name' => 'Archive',
            'parent_id' => $inbox->id,
        ]);
    }
}