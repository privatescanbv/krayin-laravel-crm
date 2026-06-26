<?php

namespace App\Services;

use Illuminate\Support\Collection;
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

    public function unreadList(int $limit = 100): Collection
    {
        return Email::where('is_read', false)
            ->whereHas('folder', fn ($q) => $q->where('name', EmailFolderEnum::INBOX->getFolderName()))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'subject', 'from', 'reply_to', 'created_at', 'lead_id', 'order_id', 'person_id']);
    }
}
