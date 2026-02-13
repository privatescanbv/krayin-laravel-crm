<?php

namespace Webkul\Admin\Http\Controllers\Concerns;

use Illuminate\Support\Collection;
use Webkul\Email\Models\Email;
use Webkul\Email\Repositories\AttachmentRepository;
use Webkul\User\Models\User;
use Webkul\Email\Models\Folder;
use Webkul\Contact\Models\Person;

trait ConcatsEmailActivities
{
    /**
     * Fetch thread emails for a supported entity type.
     *
     * Supported:
     * - lead
     * - sales
     * - clinic
     * - person (includes person's leads)
     */
    protected function getEmailsForEntityThread(string $entityType, int $entityId): Collection
    {
        return match ($entityType) {
            'lead'   => Email::forLeadThread($entityId)->get(),
            'sales'  => Email::forSalesLeadThread($entityId)->get(),
            'clinic' => Email::forClinicThread($entityId)->get(),
            'person' => (function () use ($entityId) {
                $person = Person::findOrFail($entityId);
                $leadIds = $person->leads->pluck('id')->toArray();
                $salesLeadIds = $person->salesLeads->pluck('id')->toArray();
                return Email::forPersonThread($entityId, $leadIds, $salesLeadIds)->get();
            })(),
            default  => collect(),
        };
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

        $mapped = $emails->map(function (Email $email) use ($user, $attachmentRepository) {
            $subject = $email->getThreadChain()->pluck('subject')
                ->implode(' / ');
            return (object) [
                'id'            => $email->id,
                'parent_id'     => $email->parent_id,
                'title'         => $subject,
                'type'          => 'email',
                'is_done'       => 1,
                'is_read'       => $email->is_read,
                'comment'       => $email->reply,
                'schedule_from' => null,
                'schedule_to'   => null,
                'user'          => $user,
                'group'         => null,
                'location'      => null,
                'additional'    => [
                    'folders' => $email->folder_id ? [Folder::find($email->folder_id)?->name] : [],
                    'from'    => $this->toArray($email->from),
                    'to'      => $this->toArray($email->reply_to),
                    'cc'      => $this->toArray($email->cc),
                    'bcc'     => $this->toArray($email->bcc),
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
                'emailLinkedEntityType' => ($email->person_id ? 'person' : ($email->lead_id ? 'lead' : ($email->sales_lead_id ? 'sales' : ($email->clinic_id ? 'clinic' : 'unknown')))),
                'created_at'    => $email->created_at,
                'updated_at'    => $email->updated_at,
            ];
        });

        return $activities->concat($mapped)->sortByDesc('id')->sortByDesc('created_at');
    }

    private function toArray($value): ?array {
        return is_array($value)
            ? $value
            : json_decode($value, true);
    }
}

