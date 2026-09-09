<?php

namespace App\Services;

use Webkul\Email\Enums\EmailFolderEnum;
use Webkul\Email\Models\Email;

class EmailInboxService
{
    public function unreadCount(): int
    {
        return Email::where('is_read', false)
            ->whereHas('folder', fn ($q) => $q->where('name', EmailFolderEnum::INBOX->getFolderName()))
            ->count();
    }
}
