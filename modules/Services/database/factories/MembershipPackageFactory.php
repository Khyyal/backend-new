<?php

namespace Modules\Services\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Services\Models\Membership;
use Modules\Services\Models\MembershipPackage;
use Modules\Services\Models\ServicePackage;

class MembershipPackageFactory extends Factory
{
    protected $model = MembershipPackage::class;

    public function definition(): array
    {
        return [
            'membership_id' => Membership::factory(),
            'service_package_id' => ServicePackage::factory(),
            'price_option_id' => fn (array $attributes) => ServicePackage::query()->findOrFail($attributes['service_package_id'])->price_option_id,
            'trainer_id' => null,
        ];
    }
}
