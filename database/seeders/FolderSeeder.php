<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Webkul\Email\Enums\EmailFolderEnum;
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
            'name'         => EmailFolderEnum::INBOX->getFolderName(),
            'parent_id'    => null,
            'order'        => 1,
            'is_deletable' => false,
        ]);

        $imported = Folder::create([
            'name'         => EmailFolderEnum::IMPORTED->getFolderName(),
            'parent_id'    => null,
            'order'        => 2,
            'is_deletable' => false,
        ]);

        // Create some subfolders for better organization
        Folder::create([
            'name'         => EmailFolderEnum::SENT->getFolderName(),
            'parent_id'    => null,
            'order'        => 3,
            'is_deletable' => false,
        ]);

        Folder::create([
            'name'         => EmailFolderEnum::DRAFT->getFolderName(),
            'parent_id'    => null,
            'order'        => 4,
            'is_deletable' => false,
        ]);

        Folder::create([
            'name'         => EmailFolderEnum::TRASH->getFolderName(),
            'parent_id'    => null,
            'order'        => 5,
            'is_deletable' => false,
        ]);

        // Create subfolders under Inbox
        Folder::create([
            'name'         => EmailFolderEnum::IMPORTANT->getFolderName(),
            'parent_id'    => $inbox->id,
            'order'        => 1,
            'is_deletable' => false,
        ]);

        Folder::create([
            'name'         => EmailFolderEnum::ARCHIVE->getFolderName(),
            'parent_id'    => $inbox->id,
            'order'        => 2,
            'is_deletable' => false,
        ]);
    }
}
