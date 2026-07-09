<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Webkul\Email\Enums\EmailFolderEnum;
use Webkul\Email\Models\Folder;

class FolderSeeder extends Seeder
{
    /**
     * Root folders in the order they should appear in the mail sidebar.
     *
     * @var array<int, EmailFolderEnum>
     */
    private array $rootFolders = [
        EmailFolderEnum::INBOX,
        EmailFolderEnum::INBOX_HERNIAPOLI,
        EmailFolderEnum::DRAFT,
        EmailFolderEnum::PROCESSED,
        EmailFolderEnum::NO_FOLLOW_UP,
        EmailFolderEnum::SENT_PRIVATESCAN,
        EmailFolderEnum::SENT_HERNIAPOLI,
        EmailFolderEnum::TRASH,
    ];

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        foreach ($this->rootFolders as $order => $folder) {
            Folder::updateOrCreate(
                ['name' => $folder->getFolderName()],
                [
                    'parent_id'    => null,
                    'order'        => $order + 1,
                    'is_deletable' => false,
                ]
            );
        }
    }
}
