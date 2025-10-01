<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\PartnerProduct;
use App\Models\ResourceType;
use Illuminate\Database\Eloquent\Factories\Factory;

class PartnerProductFactory extends Factory
{
    protected $model = PartnerProduct::class;

    public function definition(): array
    {
        return [
            'name'                         => $this->faker->unique()->words(3, true),
            'currency'                     => 'EUR',
            'sales_price'                  => $this->faker->randomFloat(2, 10, 2000),
            'active'                       => true,
            'description'                  => $this->faker->sentence(8),
            'discount_info'                => $this->faker->boolean(30) ? $this->faker->sentence(6) : null,
            'resource_type_id'             => function () {
                $existingId = ResourceType::query()->value('id');

                return $existingId ?? ResourceType::factory()->create()->id;
            },
            'clinic_description'           => $this->faker->boolean(50) ? $this->faker->sentence(10) : null,
            'duration'                     => $this->faker->numberBetween(15, 240),
            'purchase_price_misc'          => $this->faker->randomFloat(2, 0, 100),
            'purchase_price_doctor'        => $this->faker->randomFloat(2, 0, 200),
            'purchase_price_cardiology'    => $this->faker->randomFloat(2, 0, 150),
            'purchase_price_clinic'        => $this->faker->randomFloat(2, 0, 100),
            'purchase_price_royal_doctors' => $this->faker->randomFloat(2, 0, 80),
            'purchase_price_radiology'     => $this->faker->randomFloat(2, 0, 120),
            'purchase_price'               => 0, // Will be calculated
        ];
    }

    public function configure()
    {
        return $this->afterMaking(function (PartnerProduct $partnerProduct) {
            // Calculate total purchase price before saving
            $partnerProduct->purchase_price =
                ($partnerProduct->purchase_price_misc ?? 0) +
                ($partnerProduct->purchase_price_doctor ?? 0) +
                ($partnerProduct->purchase_price_cardiology ?? 0) +
                ($partnerProduct->purchase_price_clinic ?? 0) +
                ($partnerProduct->purchase_price_royal_doctors ?? 0) +
                ($partnerProduct->purchase_price_radiology ?? 0);
        })->afterCreating(function (PartnerProduct $partnerProduct) {
            $clinicId = Clinic::query()->value('id');
            if (! $clinicId) {
                $clinicId = Clinic::factory()->create()->id;
            }
            $partnerProduct->clinics()->sync([$clinicId]);
        });
    }
}
