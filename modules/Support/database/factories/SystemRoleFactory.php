<?php

namespace Modules\Support\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Support\Models\SystemRole;

/**
 * @extends Factory<SystemRole>
 */
class SystemRoleFactory extends Factory
{
    protected $model = SystemRole::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'role_'.fake()->unique()->lexify('????????'),
            'description' => fake()->sentence(),
        ];
    }
}
