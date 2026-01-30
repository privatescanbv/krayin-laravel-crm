<?php

namespace Database\Factories;

use App\Enums\ContactLabel;
use App\Enums\Departments;
use App\Enums\LostReason;
use App\Models\Address;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Source;
use Webkul\Lead\Models\Stage;
use Webkul\Lead\Models\Type;
use Webkul\User\Models\User;

/**
 * @extends Factory<Lead>
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
        $person = null; // Person is optional for leads
        $user = User::first() ?? User::factory()->create();
        $source = Source::first() ?? Source::create(['name' => 'Website']);
        $type = Type::first() ?? Type::create(['name' => 'New Lead']);

        // Get or create pipeline and stage
        $pipeline = Pipeline::first();
        if (! $pipeline) {
            $pipeline = Pipeline::create([
                'name'        => 'Default Pipeline',
                'is_default'  => 1,
                'rotten_days' => 30,
            ]);
        }

        $stage = Stage::where('lead_pipeline_id', $pipeline->id)->first();
        if (! $stage) {
            $stage = Stage::create([
                'name'             => 'New',
                'code'             => 'new',
                'lead_pipeline_id' => $pipeline->id,
                'sort_order'       => 1,
            ]);
        }

        // Get or create default department
        $department = Department::where('name', Departments::PRIVATESCAN->value)->first();
        if (! $department) {
            $department = Department::create(['name' => Departments::PRIVATESCAN->value]);
        }

        return [
            'description'            => $this->faker->paragraph(),
            'status'                 => $this->faker->boolean(),
            'lost_reason'            => $this->faker->optional()->randomElement(LostReason::cases())?->value,
            'closed_at'              => $this->faker->optional()->dateTimeBetween('-1 month', 'now'),
            'user_id'                => $user->id,
            'lead_source_id'         => $source->id,
            'lead_type_id'           => $type->id,
            'lead_pipeline_id'       => $pipeline->id,
            'lead_pipeline_stage_id' => $stage->id,
            'department_id'          => $department->id, // Always set department_id
            // Personal fields - minimal defaults, can be overridden
            'first_name'             => $this->faker->firstName(),
            'last_name'              => $this->faker->lastName(),
            'emails'                 => [['value' => $this->faker->email(), 'label' => ContactLabel::Eigen->value, 'is_default' => true]],
            'phones'                 => [['value' => $this->faker->randomNumber(9), 'label' => ContactLabel::Relatie->value, 'is_default' => true]],
            'lastname_prefix'        => null,
            'married_name'           => null,
            'married_name_prefix'    => null,
            'initials'               => null,
            'salutation'             => null,
            'gender'                 => null,
            'date_of_birth'          => null,
        ];
    }

    /**
     * Add personal data for leads that need name attributes.
     */
    public function withPersonalData(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'first_name'    => $this->faker->firstName(),
                'last_name'     => $this->faker->lastName(),
                'emails'        => [['value' => $this->faker->email(), 'label' => ContactLabel::Eigen->value, 'is_default' => true]],
                'phones'        => [['value' => $this->faker->randomNumber(9), 'label' => ContactLabel::Relatie->value, 'is_default' => true]],
            ];
        });
    }

    /**
     * Indicate that the lead should have an address.
     */
    public function withAddress(): static
    {
        return $this->afterCreating(function (Lead $lead) {
            $address = Address::factory()->create();
            $lead->update(['address_id' => $address->id]);
        });
    }

    /**
     * Indicate that the lead should have persons.
     */
    public function withPersons(int $count = 1): static
    {
        return $this->afterCreating(function (Lead $lead) use ($count) {
            $persons = Person::factory()->count($count)->create();
            $lead->persons()->attach($persons->pluck('id'));
        });
    }

    /**
     * Indicate that the lead is closed.
     *
     * @return Factory
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
     * @return Factory
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
     * @return Factory
     */
    public function highValue()
    {
        return $this->state(function (array $attributes) {
            return [
                // Lead value field has been removed
            ];
        });
    }
}
