<?php

namespace Database\Factories;

use App\Models\SalesLead;
use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;
use Webkul\User\Models\User;

/**
 * @extends Factory<SalesLead>
 */
class SalesLeadFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SalesLead::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        // Get or create required related models
        $stage = Stage::first();
        if (! $stage) {
            // Create a default pipeline and stage for testing
            $pipeline = Pipeline::first() ?? Pipeline::create([
                'name'        => 'Default Pipeline',
                'is_default'  => 1,
                'rotten_days' => 30,
            ]);

            $stage = Stage::create([
                'name'             => 'New',
                'code'             => 'new',
                'lead_pipeline_id' => $pipeline->id,
                'sort_order'       => 1,
            ]);
        }

        return [
            'name'               => $this->faker->sentence(3),
            'description'        => $this->faker->optional()->paragraph(),
            'pipeline_stage_id'  => $stage->id,
            'lead_id'            => null, // Optional - can be set when creating
            'user_id'            => User::first()?->id, // Optional - assign to first user if available
        ];
    }

    /**
     * Indicate that the sales lead should have a lead.
     */
    public function withLead(?Lead $lead = null): static
    {
        return $this->state(function (array $attributes) use ($lead) {
            return [
                'lead_id' => $lead?->id ?? Lead::factory()->create()->id,
            ];
        });
    }

    /**
     * Indicate that the sales lead should have a user.
     */
    public function withUser(?User $user = null): static
    {
        return $this->state(function (array $attributes) use ($user) {
            return [
                'user_id' => $user?->id ?? User::first()?->id ?? User::factory()->create()->id,
            ];
        });
    }
}
