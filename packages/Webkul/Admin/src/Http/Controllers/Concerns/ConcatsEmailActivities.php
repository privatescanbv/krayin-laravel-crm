<?php

namespace Webkul\Admin\Http\Controllers\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Webkul\Email\Repositories\AttachmentRepository;
use Webkul\User\Models\User;
use Webkul\Email\Models\Folder;

trait ConcatsEmailActivities
{
    /**
     * Map raw email rows into activity-like objects and concatenate with given activities.
     */
    protected function concatEmails(Collection $activities, Collection $emails, AttachmentRepository $attachmentRepository): Collection
    {
        // Get the first active user as fallback if auth is not available
        $user = auth()->guard('user')->user() ?? User::query()->where('status', 1)->first();

        if (! $user) {
            return $activities;
        }

        $mapped = $emails->map(function ($email) use ($user, $attachmentRepository) {
            return (object) [
                'id'            => $email->id,
                'parent_id'     => $email->parent_id,
                'title'         => $email->subject,
                'type'          => 'email',
                'is_done'       => 1,
                'comment'       => $email->reply,
                'schedule_from' => null,
                'schedule_to'   => null,
                'user'          => $user,
                'group'         => null,
                'location'      => null,
                'additional'    => [
                    'folders' => $email->folder_id ? [Folder::find($email->folder_id)?->name] : [],
                    'from'    => json_decode($email->from),
                    'to'      => json_decode($email->reply_to),
                    'cc'      => json_decode($email->cc),
                    'bcc'     => json_decode($email->bcc),
                ],
                'files'         => $attachmentRepository->findWhere(['email_id' => $email->id])->map(function ($attachment) {
                    return (object) [
                        'id'                  => $attachment->id,
                        'name'                => $attachment->name,
                        'path'                => $attachment->path,
                        'url'                 => $attachment->url,
                        'is_email_attachment' => true,
                        'created_at'          => $attachment->created_at,
                        'updated_at'          => $attachment->updated_at,
                    ];
                })->toArray(),
                'emailLinkedEntityType' => $email->activity_id ? 'activity' : ($email->person_id ? 'person' : ($email->lead_id ? 'lead' : ($email->sales_lead_id ? 'sales' : 'unknown'))),
                'created_at'    => $email->created_at,
                'updated_at'    => $email->updated_at,
            ];
        });

        return $activities->concat($mapped)->sortByDesc('id')->sortByDesc('created_at');
    }
}

