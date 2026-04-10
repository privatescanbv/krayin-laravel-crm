<?php

namespace Database\Factories;

use App\Enums\PurchasePriceType;
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

            $misc = $this->faker->randomFloat(2, 0, 100);
            $doctor = $this->faker->randomFloat(2, 0, 200);
            $cardio = $this->faker->randomFloat(2, 0, 150);
            $clinic = $this->faker->randomFloat(2, 0, 100);
            $radio = $this->faker->randomFloat(2, 0, 120);
            $partnerProduct->purchasePrice()->create([
                'type'                       => PurchasePriceType::MAIN,
                'purchase_price_misc'        => $misc,
                'purchase_price_doctor'      => $doctor,
                'purchase_price_cardiology'  => $cardio,
                'purchase_price_clinic'      => $clinic,
                'purchase_price_radiology'   => $radio,
                'purchase_price'             => $misc + $doctor + $cardio + $clinic + $radio,
            ]);

            $rMisc = $this->faker->randomFloat(2, 0, 50);
            $rDoctor = $this->faker->randomFloat(2, 0, 100);
            $rCardio = $this->faker->randomFloat(2, 0, 75);
            $rClinic = $this->faker->randomFloat(2, 0, 50);
            $rRadio = $this->faker->randomFloat(2, 0, 60);
            $partnerProduct->relatedPurchasePrice()->create([
                'type'                       => PurchasePriceType::RELATED,
                'purchase_price_misc'        => $rMisc,
                'purchase_price_doctor'      => $rDoctor,
                'purchase_price_cardiology'  => $rCardio,
                'purchase_price_clinic'      => $rClinic,
                'purchase_price_radiology'   => $rRadio,
                'purchase_price'             => $rMisc + $rDoctor + $rCardio + $rClinic + $rRadio,
            ]);
        });
    }
}
