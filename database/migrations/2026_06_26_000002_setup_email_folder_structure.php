<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Webkul\Email\Enums\EmailFolderEnum;

/**
 * Consolidates all email folder changes introduced in the multi-mailbox feature:
 * - Rename legacy "Inbox" → "Inbox Privatescan" and "Sent" → "Sent Privatescan"
 * - Ensure "Inbox Herniapoli" and "Sent HerniaPoli" root folders exist
 * - Remove old inbox subfolders (Hernia Poli webforms, Privatescan webforms,
 *   Klinieken, Nieuwsbrief reacties) and move their emails to main inbox
 */
return new class extends Migration
{
    private const string LEGACY_INBOX_NAME = 'Inbox';

    private const string LEGACY_SENT_NAME = 'Sent';

    private array $subFoldersToRemove = [
        'Hernia Poli webforms',
        'Privatescan webforms',
        'Klinieken',
        'Nieuwsbrief reacties',
    ];

    public function up(): void
    {
        // Nothing to migrate on a fresh install; FolderSeeder owns the initial folder set.
        if (! DB::table('folders')->exists()) {
            return;
        }

        $this->renameFolder(self::LEGACY_INBOX_NAME, EmailFolderEnum::INBOX->value);
        $this->renameFolder(self::LEGACY_SENT_NAME, EmailFolderEnum::SENT_PRIVATESCAN->value);

        $this->ensureRootFolderAfter(
            EmailFolderEnum::INBOX_HERNIAPOLI->value,
            EmailFolderEnum::INBOX->value
        );

        $this->ensureRootFolderAfter(
            EmailFolderEnum::SENT_HERNIAPOLI->value,
            EmailFolderEnum::SENT_PRIVATESCAN->value
        );

        $inboxId = DB::table('folders')
            ->where('name', EmailFolderEnum::INBOX->value)
            ->value('id');

        foreach ($this->subFoldersToRemove as $name) {
            $folderId = DB::table('folders')->where('name', $name)->value('id');

            if (! $folderId) {
                continue;
            }

            if ($inboxId) {
                DB::table('emails')->where('folder_id', $folderId)->update(['folder_id' => $inboxId]);
            }

            DB::table('folders')->where('id', $folderId)->delete();
        }
    }

    public function down(): void
    {
        DB::table('folders')->where('name', EmailFolderEnum::INBOX_HERNIAPOLI->value)->delete();
        DB::table('folders')->where('name', EmailFolderEnum::SENT_HERNIAPOLI->value)->delete();

        $this->renameFolder(EmailFolderEnum::INBOX->value, self::LEGACY_INBOX_NAME);
        $this->renameFolder(EmailFolderEnum::SENT_PRIVATESCAN->value, self::LEGACY_SENT_NAME);
    }

    private function renameFolder(string $from, string $to): void
    {
        DB::table('folders')
            ->whereNull('parent_id')
            ->where('name', $from)
            ->update(['name' => $to, 'updated_at' => now()]);
    }

    private function ensureRootFolderAfter(string $folderName, string $afterFolderName): void
    {
        if (DB::table('folders')->where('name', $folderName)->exists()) {
            return;
        }

        $afterOrder = DB::table('folders')
            ->where('name', $afterFolderName)
            ->value('order') ?? 0;

        DB::table('folders')
            ->whereNull('parent_id')
            ->where('order', '>', $afterOrder)
            ->increment('order');

        DB::table('folders')->insert([
            'name'         => $folderName,
            'parent_id'    => null,
            'order'        => $afterOrder + 1,
            'is_deletable' => false,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }
};
