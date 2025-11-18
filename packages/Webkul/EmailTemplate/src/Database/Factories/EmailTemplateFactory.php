<?php

namespace Webkul\EmailTemplate\Database\Factories;

use App\Enums\EmailTemplateLanguage;
use App\Enums\EmailTemplateType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\EmailTemplate\Models\EmailTemplate;

class EmailTemplateFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = EmailTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'code' => $this->faker->unique()->slug(),
            'type' => EmailTemplateType::ALGEMEEN->value,
            'language' => EmailTemplateLanguage::NEDERLANDS->value,
            'departments' => null,
            'subject' => $this->faker->sentence(),
            'content' => '<p>' . $this->faker->paragraph() . '</p>',
        ];
    }
}

