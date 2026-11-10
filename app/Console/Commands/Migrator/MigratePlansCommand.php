<?php

namespace App\Console\Commands\Migrator;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Billing\Enums\BillingInterval;
use Modules\Billing\Enums\PlanStatus;

#[Signature('migrator:plans {--dry-run : Run inside a transaction and roll it back}')]
#[Description('Copy legacy account plans into monthly plans, reusing plans that already have the same slug')]
class MigratePlansCommand extends LegacyMigrator
{
    public const ENTITY = 'plan';

    /** @var array<int, string> Legacy feature keys that have a new equivalent. */
    private const MAPPED_FEATURES = ['duration', 'max_services'];

    /** @var array<string, true> */
    private array $droppedFeatures = [];

    protected function migrate(): void
    {
        $servicesLimitId = DB::table('limits')->where('key', 'services')->value('id');

        $this->chunkLegacy('account_plans', function (Collection $plans) use ($servicesLimitId): void {
            foreach ($plans as $plan) {
                if ($this->mappedId(self::ENTITY, $plan->id) !== null) {
                    $this->tally('plans already present');

                    continue;
                }

                $slug = Str::slug((string) $plan->name) ?: "plan-{$plan->id}";
                $existingId = DB::table('plans')->where('slug', $slug)->value('id');

                if ($existingId !== null) {
                    $this->mapId(self::ENTITY, $plan->id, (int) $existingId);
                    $this->tally('plans reused by slug');

                    continue;
                }

                $this->mapId(self::ENTITY, $plan->id, $this->insertPlan($plan, $slug, $servicesLimitId));
                $this->tally('plans');
            }
        });

        if ($this->droppedFeatures !== []) {
            $this->addWarning('Legacy plan features with no new equivalent were dropped: '.implode(', ', array_keys($this->droppedFeatures)).'.');
        }

        if (($this->stats['plans'] ?? 0) > 0) {
            $this->addWarning('Plans are migrated as monthly plans; legacy yearly prices are not kept.');
        }
    }

    private function insertPlan(object $plan, string $slug, mixed $servicesLimitId): int
    {
        $features = json_decode((string) $plan->features, true);
        $features = is_array($features) ? $features : [];
        $name = Str::headline((string) $plan->name);

        foreach (array_keys($features) as $key) {
            if (! in_array($key, self::MAPPED_FEATURES, true)) {
                $this->droppedFeatures[$key] = true;
            }
        }

        $planId = DB::table('plans')->insertGetId([
            'name' => json_encode(['en' => $name, 'ar' => $name], JSON_UNESCAPED_UNICODE),
            'slug' => $slug,
            'description' => blank($plan->description) ? null : json_encode(['en' => $plan->description], JSON_UNESCAPED_UNICODE),
            'price' => $this->halalas($plan->monthly_price),
            'billing_interval' => BillingInterval::Monthly->value,
            'display_features' => null,
            'status' => ($plan->deleted_at === null ? PlanStatus::Active : PlanStatus::InActive)->value,
            'trial_days' => max(0, (int) ($features['duration'] ?? 0)),
            'created_at' => $plan->created_at,
            'updated_at' => $plan->updated_at,
        ]);

        if ($servicesLimitId !== null && is_int($features['max_services'] ?? null)) {
            DB::table('plan_limits')->insert([
                'plan_id' => $planId,
                'limit_id' => $servicesLimitId,
                'value' => $features['max_services'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->tally('plan limits');
        }

        return (int) $planId;
    }
}
