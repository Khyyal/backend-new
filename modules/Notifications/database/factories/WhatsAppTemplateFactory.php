<?php

namespace Modules\Notifications\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Notifications\Models\WhatsAppTemplate;

/**
 * @extends Factory<WhatsAppTemplate>
 */
class WhatsAppTemplateFactory extends Factory
{
    protected $model = WhatsAppTemplate::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'provider_name' => fake()->unique()->slug(2),
            'language' => 'ar',
            'body' => 'Hello {{1}}, use code {{2}}.',
            'variables' => [
                ['label' => 'Name', 'default_source' => 'recipient', 'default_field' => 'first_name'],
                ['label' => 'Code', 'default_source' => 'fixed', 'default_field' => null],
            ],
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
