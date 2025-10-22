<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Webkul\Email\Models\Email;
use Webkul\Email\Models\Folder;

class MigrateEmailFoldersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Get all emails that still have the old folders JSON field
        $emails = Email::whereNotNull('folders')->get();

        foreach ($emails as $email) {
            $folders = $email->folders;
            
            if (is_array($folders) && count($folders) > 0) {
                // Get the first folder name (assuming emails can only be in one folder now)
                $folderName = $folders[0];
                
                // Find the folder by name
                $folder = Folder::where('name', $folderName)->first();
                
                if ($folder) {
                    // Update the email to use the new folder_id
                    $email->update([
                        'folder_id' => $folder->id,
                        'folders' => null, // Remove the old folders field
                    ]);
                }
            }
        }

        $this->command->info('Email folders migration completed successfully.');
    }
}