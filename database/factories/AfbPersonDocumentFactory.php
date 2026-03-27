<?php

namespace Database\Factories;

use App\Models\AfbDispatch;
use App\Models\AfbPersonDocument;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\Contact\Models\Person;

/**
 * @extends Factory<AfbPersonDocument>
 */
class AfbPersonDocumentFactory extends Factory
{
    protected $model = AfbPersonDocument::class;

    public function definition(): array
    {
        return [
            'afb_dispatch_id' => AfbDispatch::factory(),
            'order_id'        => Order::factory(),
            'person_id'       => Person::factory(),
            'patient_name'    => $this->faker->name(),
            'file_name'       => 'afb_'.$this->faker->uuid().'.pdf',
            'file_path'       => 'afb/afb_'.$this->faker->uuid().'.pdf',
            'sent_at'         => now(),
        ];
    }
}
