<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Webkul\Email\Models\Email;

class EmailInboxService
{
    public function unreadCount(): int
    {
        return Email::where('is_read', false)
            ->whereHas('folder', fn ($q) => $q->where('name', 'Inbox'))
            ->count();
    }

    public function unreadList(int $limit = 100): Collection
    {
        return Email::where('is_read', false)
            ->whereHas('folder', fn ($q) => $q->where('name', 'Inbox'))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'subject', 'from', 'reply_to', 'created_at', 'lead_id', 'order_id', 'person_id']);
    }
}
