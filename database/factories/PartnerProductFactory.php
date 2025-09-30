<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\PartnerProduct;
use App\Models\Resource;
use App\Models\ResourceType;
use Illuminate\Database\Eloquent\Factories\Factory;

class PartnerProductFactory extends Factory
{
    protected $model = PartnerProduct::class;

    public function definition(): array
    {
        return [
            'name'               => $this->faker->unique()->words(3, true),
            'currency'           => 'EUR',
            'sales_price'        => $this->faker->randomFloat(2, 10, 2000),
            'active'             => true,
            'description'        => $this->faker->sentence(8),
            'discount_info'      => $this->faker->boolean(30) ? $this->faker->sentence(6) : null,
            'resource_type_id'   => function () {
                $existingId = ResourceType::query()->value('id');

                return $existingId ?? ResourceType::factory()->create()->id;
            },
            'partner_name'       => $this->faker->unique()->company(),
            'clinic_description' => $this->faker->boolean(50) ? $this->faker->sentence(10) : null,
            'duration'           => $this->faker->numberBetween(15, 240),
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (PartnerProduct $partnerProduct) {
            $clinicId = Clinic::query()->value('id');
            if (! $clinicId) {
                $clinicId = Clinic::factory()->create()->id;
            }
            $partnerProduct->clinics()->sync([$clinicId]);

            // Optionally attach resources if they exist
            $resourceIds = Resource::query()->limit(2)->pluck('id')->toArray();
            if (! empty($resourceIds)) {
                $partnerProduct->resources()->sync($resourceIds);
            }
        });
    }
}
