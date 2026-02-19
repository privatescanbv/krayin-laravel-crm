<?php

namespace App\Repositories;

use App\Enums\ActivityType;
use App\Enums\AppointmentTimeFilter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Webkul\Activity\Models\Activity;
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
     *
     * Only returns activities with publish_to_portal = true that are linked to the
     * person via any known relation: direct, lead, sales lead, or order.
     */
    public function queryPatientActivitiesForPerson(
        Person $person,
        ActivityType $type,
        ?AppointmentTimeFilter $filter = null,
        ?Carbon $now = null,
    ): Builder {
        $now = $now ?: now();

        return Activity::query()
            ->publishedToPortal()
            ->ofType($type)
            ->forPerson($person)
            ->scheduleTimeFilter($filter, $now);
    }
}
