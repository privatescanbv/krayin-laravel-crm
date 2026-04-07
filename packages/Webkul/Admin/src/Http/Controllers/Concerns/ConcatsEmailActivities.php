<?php

namespace Webkul\Admin\Http\Controllers\Concerns;

use Illuminate\Support\Collection;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Email\Models\Email;
use Webkul\Email\Models\Folder;
use Webkul\Email\Repositories\AttachmentRepository;
use Webkul\User\Models\User;

trait ConcatsEmailActivities
{
    /**
     * Fetch latest-per-thread emails for a supported entity type.
     */
    protected function getEmailsForEntityThread(string $entityType, int $entityId): Collection
    {
        $query = match ($entityType) {
            'lead'   => Email::forLeadThread($entityId),
            'sales'  => Email::forSalesLeadThread($entityId),
            'clinic' => Email::forClinicThread($entityId),
            'person' => $this->personThreadQuery($entityId),
            default  => null,
        };

        return $query?->get() ?? collect();
    }

    private function personThreadQuery(int $personId)
    {
        $person = Person::findOrFail($personId);

        return Email::forPersonThread(
            $personId,
            $person->leads->pluck('id')->toArray(),
            $person->salesLeads->pluck('id')->toArray(),
        );
    }

    /**
     * Convenience: concatenate "email activities" for an entity type into an existing activities collection.
     */
    protected function concatEmailActivitiesFor(string $entityType, int $entityId, Collection $activities, AttachmentRepository $attachmentRepository): Collection
    {
        $emails = $this->getEmailsForEntityThread($entityType, $entityId);

        return $this->concatEmails($activities, $emails, $attachmentRepository);
    }

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

        // Bulk-load linked activities to avoid N+1
        $activityIds = $emails->pluck('activity_id')->filter()->unique()->values()->all();
        $activities = $activityIds
            ? Activity::whereIn('id', $activityIds)->get()->keyBy('id')
            : collect();

        $mapped = $emails->map(function (Email $email) use ($user, $attachmentRepository, $activities) {
            $subject = $email->getThreadChain()->pluck('subject')
                ->implode(' / ');

            $linkedActivity = $email->activity_id ? ($activities[$email->activity_id] ?? null) : null;
            $folder = $email->folder_id ? Folder::find($email->folder_id) : null;

            $linkedEntityType = $linkedActivity
                ? 'activity'
                : ($email->person_id ? 'person' : ($email->lead_id ? 'lead' : ($email->sales_lead_id ? 'sales' : ($email->clinic_id ? 'clinic' : 'unknown'))));

            return (object) [
                'id'             => $email->id,
                'parent_id'      => $email->parent_id,
                'title'          => $subject,
                'type'           => 'email',
                'is_done'        => 1,
                'is_read'        => $email->is_read,
                'comment'        => $email->reply,
                'schedule_from'  => null,
                'schedule_to'    => null,
                'user'           => $user,
                'group'          => null,
                'location'       => null,
                'additional'     => [
                    'folders' => $folder ? [$folder->name] : [],
                    'from'    => $this->toArray($email->from),
                    'to'      => $this->toArray($email->reply_to),
                    'cc'      => $this->toArray($email->cc),
                    'bcc'     => $this->toArray($email->bcc),
                ],
                'files'          => $attachmentRepository->findWhere(['email_id' => $email->id])->map(function ($attachment) {
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
                'emailLinkedEntityType' => $linkedEntityType,
                'activity_id'    => $email->activity_id,
                'activity_title' => $linkedActivity?->title,
                'activity_type'  => $linkedActivity?->type?->value,
                'folder_name'    => $folder?->name,
                'created_at'     => $email->created_at,
                'updated_at'     => $email->updated_at,
            ];
        });

        return $activities->concat($mapped)->sortByDesc('id')->sortByDesc('created_at');
    }

    private function toArray($value): ?array
    {
        return is_array($value)
            ? $value
            : json_decode($value, true);
    }
}
