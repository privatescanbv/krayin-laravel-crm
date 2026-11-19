<?php

namespace App\Repositories;

use App\Enums\ActivityType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Webkul\Activity\Models\Activity;
use Webkul\Activity\Models\File as ActivityFile;
use Webkul\Contact\Models\Person;
use Webkul\Core\Eloquent\Repository;

class ActivityRepository extends Repository
{
    public function model(): string
    {
        return Activity::class;
    }

    /**
     * Paginate document files (FILE activities) published to a person's portal.
     *
     * Visibility always goes through the central `visibleInPatientPortal` scope, so a document
     * type with show_in_patient_portal = false can never leak through, even via $documentTypeIds.
     *
     * @param  array<int>|null  $documentTypeIds
     */
    public function paginateDocumentFilesForPerson(
        Person $person,
        int $perPage,
        ?array $documentTypeIds = null,
        ?int $orderIdFilter = null,
    ): LengthAwarePaginator {
        return ActivityFile::query()
            ->with(['activity.documentType'])
            ->whereHas('activity', function (Builder $q) use ($person, $documentTypeIds, $orderIdFilter) {
                $q->ofType(ActivityType::FILE)
                    ->publishedToPortalForPerson($person)
                    ->visibleInPatientPortal();

                if (! empty($documentTypeIds)) {
                    $q->whereIn('document_type_id', $documentTypeIds);
                }

                if ($orderIdFilter !== null) {
                    $q->where('order_id', $orderIdFilter);
                }
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
