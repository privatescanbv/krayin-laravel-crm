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
        $c = 0;
        // Create root folders
        $inbox = Folder::create([
            'name'         => EmailFolderEnum::INBOX->getFolderName(),
            'parent_id'    => null,
            'order'        => ++$c,
            'is_deletable' => false,
        ]);

        Folder::create([
            'name'         => EmailFolderEnum::DRAFT->getFolderName(),
            'parent_id'    => null,
            'order'        => ++$c,
            'is_deletable' => false,
        ]);

        $imported = Folder::create([
            'name'         => EmailFolderEnum::PROCESSED->getFolderName(),
            'parent_id'    => null,
            'order'        => ++$c,
            'is_deletable' => false,
        ]);

        Folder::create([
            'name'         => EmailFolderEnum::NO_FOLLOW_UP->getFolderName(),
            'parent_id'    => null,
            'order'        => ++$c,
            'is_deletable' => false,
        ]);

        // Create some subfolders for better organization
        Folder::create([
            'name'         => EmailFolderEnum::SENT->getFolderName(),
            'parent_id'    => null,
            'order'        => ++$c,
            'is_deletable' => false,
        ]);

        Folder::create([
            'name'         => EmailFolderEnum::TRASH->getFolderName(),
            'parent_id'    => null,
            'order'        => ++$c,
            'is_deletable' => false,
        ]);

        // Create subfolders under Inbox
        Folder::create([
            'name'         => EmailFolderEnum::PRIVATESCAN_WEBFORM->getFolderName(),
            'parent_id'    => $inbox->id,
            'order'        => 1,
            'is_deletable' => false,
        ]);

        Folder::create([
            'name'         => EmailFolderEnum::HERNIA_WEBFORM->getFolderName(),
            'parent_id'    => $inbox->id,
            'order'        => 2,
            'is_deletable' => false,
        ]);

        Folder::create([
            'name'         => EmailFolderEnum::CLINICS->getFolderName(),
            'parent_id'    => $inbox->id,
            'order'        => 3,
            'is_deletable' => false,
        ]);

        Folder::create([
            'name'         => EmailFolderEnum::NEWSLETTER->getFolderName(),
            'parent_id'    => $inbox->id,
            'order'        => 4,
            'is_deletable' => false,
        ]);

    }
}
