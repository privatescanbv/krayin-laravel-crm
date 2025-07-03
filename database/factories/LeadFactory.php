<?php

namespace Database\Factories;

use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineStageDefaultKeys;
use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Source;
use Webkul\Lead\Models\Type;
use Webkul\User\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Webkul\Lead\Models\Lead>
 */
class LeadFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Lead::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        // Get or create required related models
        //        $person = Person::first() ?? Person::factory()->create();
        $user = User::first() ?? WebkulUserFactory::new()->create();
        $source = Source::first() ?? Source::create(['name' => 'Website']);
        $type = Type::first() ?? Type::create(['name' => 'New Lead']);

        // Get or create pipeline and stage

        return [
            'title'                  => $this->faker->sentence(3),
            'description'            => $this->faker->paragraph(),
            'lead_value'             => $this->faker->randomFloat(2, 100, 10000),
            'status'                 => $this->faker->boolean(),
            'lost_reason'            => $this->faker->optional()->sentence(),
            'expected_close_date'    => $this->faker->optional()->dateTimeBetween('now', '+3 months'),
            'closed_at'              => $this->faker->optional()->dateTimeBetween('-1 month', 'now'),
            'user_id'                => $user->id,
            'person_id'              => $person->id ?? null,
            'lead_source_id'         => $source->id,
            'lead_type_id'           => $type->id,
            'lead_pipeline_id'       => PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ID->value,
            'lead_pipeline_stage_id' => PipelineStageDefaultKeys::PIPELINE_FIRST_STAGE_PRIVATESCAN_ID->value,
        ];
    }

    /**
     * Indicate that the lead is closed.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function closed()
    {
        return $this->state(function (array $attributes) {
            return [
                'status'    => false,
                'closed_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            ];
        });
    }

    /**
     * Indicate that the lead is active.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function active()
    {
        return $this->state(function (array $attributes) {
            return [
                'status'    => true,
                'closed_at' => null,
            ];
        });
    }

    /**
     * Indicate that the lead has a high value.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function highValue()
    {
        return $this->state(function (array $attributes) {
            return [
                'lead_value' => $this->faker->randomFloat(2, 5000, 50000),
            ];
        });
    }

    /**
     * Indicate that the lead is expected to close soon.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function closingSoon()
    {
        return $this->state(function (array $attributes) {
            return [
                'expected_close_date' => $this->faker->dateTimeBetween('now', '+1 week'),
            ];
        });
    }
}
