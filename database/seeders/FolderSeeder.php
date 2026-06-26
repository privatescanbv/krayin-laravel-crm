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
        Folder::create([
            'name'         => EmailFolderEnum::INBOX->getFolderName(),
            'parent_id'    => null,
            'order'        => ++$c,
            'is_deletable' => false,
        ]);

        Folder::create([
            'name'         => EmailFolderEnum::INBOX_HERNIAPOLI->getFolderName(),
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

        Folder::create([
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
            'name'         => EmailFolderEnum::SENT_PRIVATESCAN->getFolderName(),
            'parent_id'    => null,
            'order'        => ++$c,
            'is_deletable' => false,
        ]);

        Folder::create([
            'name'         => EmailFolderEnum::SENT_HERNIAPOLI->getFolderName(),
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

    }
}
