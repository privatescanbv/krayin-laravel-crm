<?php

namespace Database\Factories;

use App\Models\SalesLead;
use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Stage;
use Webkul\Quote\Models\Quote;
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
            throw new \Exception('No pipeline stages found. Please seed the database first.');
        }

        return [
            'name'               => $this->faker->sentence(3),
            'description'        => $this->faker->optional()->paragraph(),
            'pipeline_stage_id'  => $stage->id,
            'lead_id'            => null, // Optional - can be set when creating
            'quote_id'           => null, // Optional - can be set when creating
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
     * Indicate that the sales lead should have a quote.
     */
    public function withQuote(?Quote $quote = null): static
    {
        return $this->state(function (array $attributes) use ($quote) {
            return [
                'quote_id' => $quote?->id ?? Quote::factory()->create()->id,
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
