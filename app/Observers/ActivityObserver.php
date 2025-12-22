<?php

namespace App\Observers;

use App\Actions\Activities\CreatePatientMessageFromActivityAction;
use Webkul\Activity\Models\Activity;

class ActivityObserver
{
    public function __construct(
        protected CreatePatientMessageFromActivityAction $createPatientMessageFromActivityAction
    ) {}

    /**
     * Handle the Activity "created" event.
     */
    public function created(Activity $activity): void
    {
        $this->createPatientMessageFromActivityAction->handle($activity, 'created');
    }

    /**
     * Handle the Activity "updated" event.
     */
    public function updated(Activity $activity): void
    {
        $this->createPatientMessageFromActivityAction->handle($activity, 'updated');
    }

    /**
     * Handle the Activity "deleted" event.
     */
    public function deleted(Activity $activity): void
    {
        //
    }

    /**
     * Handle the Activity "restored" event.
     */
    public function restored(Activity $activity): void
    {
        //
    }

    /**
     * Handle the Activity "force deleted" event.
     */
    public function forceDeleted(Activity $activity): void
    {
        //
    }
}
