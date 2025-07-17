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
            'lengte'                  => $this->faker->numberBetween(150, 200),
            'gewicht'                 => $this->faker->numberBetween(50, 120),
            'metalen'                 => $this->faker->boolean(),
            'opm_metalen_c'           => $this->faker->optional()->sentence(),
            'medicijnen'              => $this->faker->boolean(),
            'opm_medicijnen_c'        => $this->faker->optional()->sentence(),
            'glaucoom'                => $this->faker->boolean(),
            'opm_glaucoom_c'          => $this->faker->optional()->sentence(),
            'claustrofobie'           => $this->faker->boolean(),
            'dormicum'                => $this->faker->boolean(),
            'hart_operatie_c'         => $this->faker->boolean(),
            'opm_hart_operatie_c'     => $this->faker->optional()->sentence(),
            'implantaat_c'            => $this->faker->boolean(),
            'opm_implantaat_c'        => $this->faker->optional()->sentence(),
            'operaties_c'             => $this->faker->boolean(),
            'opm_operaties_c'         => $this->faker->optional()->sentence(),
            'opmerking'               => $this->faker->optional()->sentence(),
            'hart_erfelijk'           => $this->faker->boolean(),
            'opm_erf_hart_c'          => $this->faker->optional()->sentence(),
            'vaat_erfelijk'           => $this->faker->boolean(),
            'opm_erf_vaat_c'          => $this->faker->optional()->sentence(),
            'tumoren_erfelijk'        => $this->faker->boolean(),
            'opm_erf_tumor_c'         => $this->faker->optional()->sentence(),
            'allergie_c'              => $this->faker->boolean(),
            'opm_allergie_c'          => $this->faker->optional()->sentence(),
            'rugklachten'             => $this->faker->boolean(),
            'opm_rugklachten_c'       => $this->faker->optional()->sentence(),
            'heart_problems'          => $this->faker->boolean(),
            'opm_hartklachten_c'      => $this->faker->optional()->sentence(),
            'smoking'                 => $this->faker->boolean(),
            'opm_roken_c'             => $this->faker->optional()->sentence(),
            'diabetes'                => $this->faker->boolean(),
            'opm_diabetes_c'          => $this->faker->optional()->sentence(),
            'spijsverteringsklachten' => $this->faker->boolean(),
            'opm_spijsvertering_c'    => $this->faker->optional()->sentence(),
            'risico_hartinfarct'      => $this->faker->words(3, true),
            'actief'                  => $this->faker->boolean(),
            'opm_advies_c'            => $this->faker->optional()->sentence(),
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
