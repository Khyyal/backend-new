<?php

namespace Modules\Exports\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Modules\Exports\Enums\ExportStatus;
use Modules\Exports\Enums\ExportType;
use Modules\Exports\Models\Export;
use Modules\Support\Database\Factories\SystemUserFactory;
use Modules\Support\Models\SystemUser;

/**
 * @extends Factory<Export>
 */
class ExportFactory extends Factory
{
    protected $model = Export::class;

    public function definition(): array
    {
        return [
            'requester_type' => (new SystemUser)->getMorphClass(),
            'requester_id' => SystemUserFactory::new(),
            'center_id' => null,
            'type' => ExportType::AdminPurchases,
            'filters' => [],
            'status' => ExportStatus::Pending,
            'disk' => 'local',
            'path' => 'exports/'.fake()->uuid().'/purchases.xlsx',
            'file_name' => 'purchases.xlsx',
        ];
    }

    public function requestedBy(Model $requester): static
    {
        return $this->state(fn (): array => [
            'requester_type' => $requester->getMorphClass(),
            'requester_id' => $requester->getKey(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => ExportStatus::Completed,
            'rows_count' => 1,
            'completed_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);
    }
}
