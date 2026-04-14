<?php

namespace App\Repositories;

use App\Enums\ActivityType;
use App\Enums\AppointmentTimeFilter;
use Carbon\Carbon;
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
     * Get a query builder for patient portal activities of a given type for a person.
     */
    public function queryPatientActivitiesForPerson(
        Person $person,
        ActivityType $type,
        ?AppointmentTimeFilter $filter = null,
        ?Carbon $now = null,
    ): Builder {
        $now = $now ?: now();

        return Activity::query()
            ->publishedToPortalForPerson($person)
            ->ofType($type)
            ->scheduleTimeFilter($filter, $now);
    }

    /**
     * Paginate document files (FILE activities) published to a person's portal.
     */
    public function paginateDocumentFilesForPerson(
        Person $person,
        int $perPage,
        ?string $documentType = null,
        ?int $orderIdFilter = null,
    ): LengthAwarePaginator {
        return ActivityFile::query()
            ->with(['activity'])
            ->whereHas('activity', function (Builder $q) use ($person, $documentType, $orderIdFilter) {
                $q->ofType(ActivityType::FILE)
                    ->publishedToPortalForPerson($person);

                if ($documentType !== null && $documentType !== '') {
                    $q->where('additional->document_type', $documentType);
                }

                if ($orderIdFilter !== null) {
                    $q->where('order_id', $orderIdFilter);
                }
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
