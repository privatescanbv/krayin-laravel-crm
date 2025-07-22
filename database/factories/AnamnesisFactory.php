<?php

namespace Database\Factories;

use App\Models\Anamnesis;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Webkul\Lead\Models\Lead;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Anamnesis>
 */
class AnamnesisFactory extends Factory
{
    protected $model = Anamnesis::class;

    public function definition(): array
    {
        return [
            'id'                      => (string) Str::uuid(),
            'name'                    => $this->faker->name(),
            'created_at'              => $this->faker->dateTime(),
            'updated_at'              => $this->faker->dateTime(),
            'updated_by'              => (string) Str::uuid(),
            'created_by'              => (string) Str::uuid(),
            'description'             => $this->faker->optional()->paragraph(),
            'deleted'                 => 0,
            'team_id'                 => (string) Str::uuid(),
            'team_set_id'             => (string) Str::uuid(),
            'assigned_user_id'        => (string) Str::uuid(),
            'comment_clinic'          => $this->faker->optional()->sentence(),
            'height'                  => $this->faker->numberBetween(150, 200),
            'weight'                  => $this->faker->numberBetween(50, 120),
            'metals'                  => $this->faker->boolean(),
            'metals_notes'            => $this->faker->optional()->sentence(),
            'medications'             => $this->faker->boolean(),
            'medications_notes'       => $this->faker->optional()->sentence(),
            'glaucoma'                => $this->faker->boolean(),
            'glaucoma_notes'          => $this->faker->optional()->sentence(),
            'claustrophobia'          => $this->faker->boolean(),
            'dormicum'                => $this->faker->boolean(),
            'heart_surgery'           => $this->faker->boolean(),
            'heart_surgery_notes'     => $this->faker->optional()->sentence(),
            'implant'                 => $this->faker->boolean(),
            'implant_notes'           => $this->faker->optional()->sentence(),
            'surgeries'               => $this->faker->boolean(),
            'surgeries_notes'         => $this->faker->optional()->sentence(),
            'remarks'                 => $this->faker->optional()->sentence(),
            'hereditary_heart'        => $this->faker->boolean(),
            'hereditary_heart_notes'  => $this->faker->optional()->sentence(),
            'hereditary_vascular'     => $this->faker->boolean(),
            'hereditary_vascular_notes' => $this->faker->optional()->sentence(),
            'hereditary_tumors'       => $this->faker->boolean(),
            'hereditary_tumors_notes' => $this->faker->optional()->sentence(),
            'allergies'               => $this->faker->boolean(),
            'allergies_notes'         => $this->faker->optional()->sentence(),
            'back_problems'           => $this->faker->boolean(),
            'back_problems_notes'     => $this->faker->optional()->sentence(),
            'heart_problems'          => $this->faker->boolean(),
            'heart_problems_notes'    => $this->faker->optional()->sentence(),
            'smoking'                 => $this->faker->boolean(),
            'smoking_notes'           => $this->faker->optional()->sentence(),
            'diabetes'                => $this->faker->boolean(),
            'diabetes_notes'          => $this->faker->optional()->sentence(),
            'digestive_problems'      => $this->faker->boolean(),
            'digestive_problems_notes'=> $this->faker->optional()->sentence(),
            'heart_attack_risk'       => $this->faker->words(3, true),
            'active'                  => $this->faker->boolean(),
            'advice_notes'            => $this->faker->optional()->sentence(),
            'lead_id'                 => Lead::factory(),
            'user_id'                 => User::factory(),
        ];
    }

    public function withLead(?Lead $lead = null)
    {
        return $this->state(function (array $attributes) {
            return [
                // Voeg hier een lead_id toe als je die in je model/migratie hebt
                // 'lead_id' => $lead ? $lead->id : Lead::factory()->create()->id,
            ];
        });
    }
}
