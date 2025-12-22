<?php

namespace Webkul\Admin\Listeners;

use App\Actions\Activities\CreatePatientMessageFromActivityAction;
use Webkul\Activity\Contracts\Activity as ActivityContract;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\Warehouse\Repositories\WarehouseRepository;

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
        protected WarehouseRepository                           $warehouseRepository,
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
        } elseif (request()->input('person_id')) {
            $person = $this->personRepository->find(request()->input('person_id'));

            if (! $person->activities->contains($activity->id)) {
                $person->activities()->attach($activity->id);
                $this->createPatientMessageFromActivityAction->handle($activity, 'afterUpdateOrCreate', $person);
            }
        } elseif (request()->input('warehouse_id')) {
            $warehouse = $this->warehouseRepository->find(request()->input('warehouse_id'));

            if (! $warehouse->activities->contains($activity->id)) {
                $warehouse->activities()->attach($activity->id);
            }
        } elseif (request()->input('product_id')) {
            $product = $this->productRepository->find(request()->input('product_id'));

            if (! $product->activities->contains($activity->id)) {
                $product->activities()->attach($activity->id);
            }
        }
    }
}
