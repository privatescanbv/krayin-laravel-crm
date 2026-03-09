<?php

namespace Webkul\Admin\Listeners;

use App\Actions\Activities\CreatePatientMessageFromActivityAction;
use Webkul\Activity\Contracts\Activity as ActivityContract;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Product\Repositories\ProductRepository;

class Activity
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected LeadRepository                                $leadRepository,
        protected PersonRepository                              $personRepository,
        protected ProductRepository                             $productRepository,
        private readonly CreatePatientMessageFromActivityAction $createPatientMessageFromActivityAction,
    ) {}

    /**
     * Link activity to lead or person.
     */
    public function afterUpdateOrCreate(ActivityContract $activity): void
    {
        // If the activity is tied to a lead, skip any person linkage
        if ($activity->lead_id || request()->input('lead_id')) {
            // lead-bound: do nothing here
        } elseif ($activity->person_id) {
            // person_id FK is already set on the activity (by the controller).
            // No pivot attach needed — just trigger the patient message action if applicable.
            $person = $this->personRepository->find($activity->person_id);

            if ($person) {
                $this->createPatientMessageFromActivityAction->handle($activity, 'afterUpdateOrCreate', $person);
            }
        }
    }
}
