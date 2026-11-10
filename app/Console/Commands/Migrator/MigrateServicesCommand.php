<?php

namespace App\Console\Commands\Migrator;

use App\Console\Commands\Migrator\Support\LegacySchedule;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Centers\Models\Center;
use Modules\Services\Enums\EventOccurrenceType;
use Modules\Services\Enums\PriceOptionUnit;
use Modules\Services\Enums\ServiceType;
use Modules\Services\Enums\VisitEnterType;
use Modules\Services\Models\Event;
use Modules\Services\Models\HorseCare;
use Modules\Services\Models\HorseService;
use Modules\Services\Models\Membership;
use Modules\Services\Models\RecreationalRiding;
use Modules\Services\Models\Resort;
use Modules\Services\Models\Service;
use Modules\Services\Models\TrainingService;
use Modules\Services\Models\Visit;
use Modules\Support\Enums\ActivationStatus;

#[Signature('migrator:services {--dry-run : Run inside a transaction and roll it back}'.LegacyMigrator::MEDIA_OPTIONS)]
#[Description('Copy legacy services, resorts, events, horse services/cares, memberships and packages into services, keeping legacy service ids')]
class MigrateServicesCommand extends LegacyMigrator
{
    public const ENTITY_EVENT = 'service:event';

    public const ENTITY_MEMBERSHIP = 'service:membership';

    public const ENTITY_HORSE_CARE = 'service:horse_care';

    public const ENTITY_HORSE_SERVICE = 'service:horse_service';

    public const ENTITY_SERVICE_PACKAGE = 'service_package';

    /** @var array<string, ServiceType> Legacy `services.service_type` → new type. */
    private const LEGACY_TYPES = [
        'free_riding' => ServiceType::RecreationRiding,
        'training' => ServiceType::Training,
        'visit' => ServiceType::Visit,
        'resort' => ServiceType::Resort,
    ];

    /** @var array<string, string> Legacy resort close flag → new resort setting key. */
    private const RESORT_FLAGS = [
        'close_all_free_riding' => 'close_all_recreational_ride_bookings',
        'close_all_training' => 'close_all_training_bookings',
        'close_all_visit' => 'close_all_visit_bookings',
        'close_all_resort' => 'close_all_resort_bookings',
    ];

    /** @var Collection<int, int> */
    private Collection $centerIds;

    /** @var array<int, array<string, true>> Slugs taken per center. */
    private array $slugs = [];

    /** @var Collection<string, object> Legacy reservable constraints keyed by "<type>:<id>". */
    private Collection $constraints;

    /**
     * Gallery rows to copy after commit: new service id → [legacy type, legacy id].
     *
     * @var array<int, array{0: string, 1: int}>
     */
    private array $imageSources = [];

    protected function migrate(): void
    {
        $this->centerIds = DB::table('centers')->pluck('id')->flip();
        $this->constraints = $this->legacy()->table('reservable_constraints')->get()
            ->keyBy(fn (object $row): string => $row->constrainable_type.':'.$row->constrainable_id);

        DB::table('services')->get(['center_id', 'slug'])
            ->each(function (object $row): void {
                $this->slugs[$row->center_id][$row->slug] = true;
            });

        $this->migrateLegacyServices();
        $this->attachTrainers();
        $this->migratePackages();
        $this->migrateEvents();
        $this->migrateHorseOffers('horse_services', HorseService::class, ServiceType::HorseService, self::ENTITY_HORSE_SERVICE, 'App\\Models\\HorseService');
        $this->migrateHorseOffers('horse_cares', HorseCare::class, ServiceType::HorseCare, self::ENTITY_HORSE_CARE, 'App\\Models\\HorseCare');
        $this->migrateMemberships();
    }

    protected function afterCommit(bool $dryRun): void
    {
        Center::flushSearchCache();

        if ($dryRun || $this->option('skip-media')) {
            $this->tally('services with images pending (dry run or --skip-media)', count($this->imageSources));

            return;
        }

        foreach ($this->imageSources as $serviceId => [$type, $legacyId]) {
            $images = $this->legacy()->table('images')
                ->where('imageable_type', $type)
                ->where('imageable_id', $legacyId)
                ->orderBy('order')
                ->orderBy('id')
                ->get();

            if ($images->isNotEmpty()) {
                $this->copyLegacyImages(Service::withTrashed()->findOrFail($serviceId), $images, 'images', "Service #{$serviceId}");
            }
        }
    }

    /**
     * Legacy `services` rows (riding, training, visit, resort). The service id
     * and the detail row id both keep the legacy service id.
     */
    private function migrateLegacyServices(): void
    {
        $existing = DB::table('services')->pluck('id')->flip();

        $this->chunkLegacy('services', function (Collection $services) use ($existing): void {
            foreach ($services as $legacy) {
                $type = self::LEGACY_TYPES[$legacy->service_type] ?? null;

                if ($type === null || ! $this->hasCenter($legacy->barn_id, "Service #{$legacy->id}")) {
                    continue;
                }

                $this->imageSources[$legacy->id] = ['App\\Models\\Service', $legacy->id];

                if ($existing->has($legacy->id)) {
                    $this->tally('services already present');

                    continue;
                }

                match ($type) {
                    ServiceType::RecreationRiding => $this->migrateRiding($legacy),
                    ServiceType::Training => $this->migrateTraining($legacy),
                    ServiceType::Visit => $this->migrateVisit($legacy),
                    ServiceType::Resort => $this->migrateResort($legacy),
                };

                $this->tally("services: {$type->value}");
            }
        });
    }

    private function migrateRiding(object $legacy): void
    {
        $this->insertDetail(RecreationalRiding::class, $legacy, [
            'max_tickets_per_hour' => $legacy->max_tickets_per_hour > 0 ? $legacy->max_tickets_per_hour : null,
        ]);

        $serviceId = $this->insertService($legacy->id, $legacy->barn_id, ServiceType::RecreationRiding, RecreationalRiding::class, $legacy->id, $legacy);
        $this->insertDurationOptions($serviceId, $legacy);
        $this->insertSchedules(RecreationalRiding::class, $legacy->id, $legacy->days, $legacy->hours, "Service #{$legacy->id}");
        $this->insertSettings($serviceId, $this->bookingSettings('App\\Models\\Service', $legacy->id));
    }

    private function migrateTraining(object $legacy): void
    {
        $this->insertDetail(TrainingService::class, $legacy);

        $serviceId = $this->insertService($legacy->id, $legacy->barn_id, ServiceType::Training, TrainingService::class, $legacy->id, $legacy);
        $this->insertDurationOptions($serviceId, $legacy);
        $this->insertSettings($serviceId, $this->bookingSettings('App\\Models\\Service', $legacy->id));
    }

    private function migrateVisit(object $legacy): void
    {
        $specificTime = $legacy->visit_type === 'specificTime';

        $this->insertDetail(Visit::class, $legacy, [
            'enter_type' => $specificTime ? VisitEnterType::SpecificTime->value : VisitEnterType::AllDay->value,
            'max_tickets_per_day' => $legacy->max_tickets_per_day > 0 ? $legacy->max_tickets_per_day : null,
        ]);

        $serviceId = $this->insertService($legacy->id, $legacy->barn_id, ServiceType::Visit, Visit::class, $legacy->id, $legacy);

        $durationPrice = $this->durationPrices($legacy)->first()['price'] ?? null;
        $price = $specificTime ? ($durationPrice ?? $legacy->tickets_price) : ($legacy->tickets_price ?? $durationPrice);

        if ($price === null) {
            $this->addWarning("Service #{$legacy->id} (visit): no ticket price; stored as 0.");
        }

        $this->insertOptions($serviceId, [['name' => null, 'price' => $this->halalas($price), 'quantity' => null]]);

        $days = LegacySchedule::days($legacy->days);

        if ($specificTime) {
            $this->insertSchedules(Visit::class, $legacy->id, $legacy->days, $legacy->hours, "Service #{$legacy->id}");
        } else {
            DB::table('schedules')->insert(LegacySchedule::scheduleRows((new Visit)->getMorphClass(), $legacy->id, $days, [], allDay: true));
        }

        $this->insertSettings($serviceId, ['enable_cash_on_arrival' => $this->cashOnArrival('App\\Models\\Service', $legacy->id)]);
    }

    private function migrateResort(object $legacy): void
    {
        $this->insertDetail(Resort::class, $legacy);
        $serviceId = $this->insertService($legacy->id, $legacy->barn_id, ServiceType::Resort, Resort::class, $legacy->id, $legacy);

        $dayPrices = $this->legacy()->table('resort_days')->where('service_id', $legacy->id)->orderBy('id')->get()
            ->map(fn (object $day): ?array => ($weekday = LegacySchedule::day($day->day)) === null
                ? null
                : ['name' => null, 'price' => $this->halalas($day->price), 'quantity' => $weekday])
            ->filter()
            ->unique('quantity');

        $addons = $this->legacy()->table('resort_additional_services')->where('service_id', $legacy->id)->orderBy('id')->get()
            ->map(fn (object $addon): array => ['name' => Str::limit((string) $addon->name, 255, ''), 'price' => $this->halalas($addon->price), 'quantity' => null]);

        if ($dayPrices->isEmpty()) {
            $this->addWarning("Service #{$legacy->id} (resort): no day prices.");
        }

        $this->insertOptions($serviceId, [...$dayPrices->values()->all(), ...$addons->all()]);

        $flags = $this->legacy()->table('resort_services_settings')->where('service_id', $legacy->id)->first();
        $settings = ['enable_cash_on_arrival' => $this->cashOnArrival('App\\Models\\Service', $legacy->id)];

        foreach (self::RESORT_FLAGS as $legacyFlag => $key) {
            $settings[$key] = (bool) ($flags->{$legacyFlag} ?? false);
        }

        $this->insertSettings($serviceId, $settings);
    }

    /**
     * Training services get the trainers linked through `trainer_service`
     * and through the trainers of their packages (`trainer_package`).
     */
    private function attachTrainers(): void
    {
        $trainingIds = DB::table('services')
            ->where('serviceable_type', (new TrainingService)->getMorphClass())
            ->pluck('center_id', 'serviceable_id');

        $trainers = DB::table('trainers')->pluck('center_id', 'id');

        $direct = $this->legacy()->table('trainer_service')->get(['trainer_id', 'service_id']);
        $viaPackages = $this->legacy()->table('trainer_package')
            ->join('packages', 'packages.id', '=', 'trainer_package.package_id')
            ->get(['trainer_package.trainer_id', 'packages.service_id']);

        $rows = $direct->concat($viaPackages)
            ->filter(fn (object $link): bool => $trainingIds->has($link->service_id)
                && $trainers->get($link->trainer_id) === $trainingIds->get($link->service_id))
            ->map(fn (object $link): array => ['trainer_id' => $link->trainer_id, 'training_service_id' => $link->service_id])
            ->unique(fn (array $row): string => $row['trainer_id'].'-'.$row['training_service_id'])
            ->values()
            ->all();

        foreach (array_chunk($rows, 500) as $chunk) {
            $this->tally('trainer links to training services', DB::table('trainer_training_service')->insertOrIgnore($chunk));
        }
    }

    /**
     * Each legacy package tier (`package_sessions`) becomes a service package.
     * The first tier of a session length reuses the service's matching minute
     * option; any other tier gets its own option (price_option_id is unique).
     */
    private function migratePackages(): void
    {
        $services = DB::table('services')
            ->whereIn('type', [ServiceType::Training->value, ServiceType::RecreationRiding->value])
            ->pluck('id')
            ->flip();

        $packages = $this->legacy()->table('packages')->orderBy('id')->get()->keyBy('id');

        $this->chunkLegacy('package_sessions', function (Collection $sessions) use ($services, $packages): void {
            foreach ($sessions as $session) {
                $package = $packages->get($session->package_id);

                if ($package === null || $package->service_id === null || ! $services->has($package->service_id)) {
                    $this->addWarning("Package tier #{$session->id}: its package or service was not migrated; skipped.");

                    continue;
                }

                if ($this->mappedId(self::ENTITY_SERVICE_PACKAGE, $session->id) !== null) {
                    $this->tally('package tiers already present');

                    continue;
                }

                $this->migratePackageTier($session, $package);
            }
        });
    }

    private function migratePackageTier(object $session, object $package): void
    {
        $serviceId = (int) $package->service_id;
        $deletedAt = $session->deleted_at ?? $package->deleted_at;
        $status = $package->status === ActivationStatus::INACTIVE->value ? ActivationStatus::INACTIVE : ActivationStatus::ACTIVE;

        $minuteOptions = DB::table('price_options')
            ->where('service_id', $serviceId)
            ->where('unit', PriceOptionUnit::MINUTE->value)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get();

        $length = $package->duration > 0 ? (int) $package->duration : (int) ($minuteOptions->first()->quantity ?? 0);

        if ($length <= 0) {
            $this->addWarning("Package tier #{$session->id}: service #{$serviceId} has no session length; skipped.");

            return;
        }

        $sameLength = $minuteOptions->where('quantity', $length);
        $withPackage = DB::table('service_packages')->whereIn('price_option_id', $sameLength->pluck('id'))->pluck('price_option_id')->flip();
        $optionId = $sameLength->first(fn (object $option): bool => ! $withPackage->has($option->id))?->id;

        if ($optionId === null) {
            $optionId = DB::table('price_options')->insertGetId([
                'service_id' => $serviceId,
                'name' => null,
                'unit' => PriceOptionUnit::MINUTE->value,
                'quantity' => $length,
                'price' => $sameLength->first()->price ?? intdiv($this->halalas($session->price), max(1, (int) $session->count)),
                'deleted_at' => $deletedAt,
            ]);
            $this->tally('extra price options for package tiers');
        }

        $packageId = DB::table('service_packages')->insertGetId([
            'service_id' => $serviceId,
            'price_option_id' => $optionId,
            'count' => max(1, (int) $session->count),
            'price' => $this->halalas($session->price),
            'duration' => max(1, (int) ($session->duration ?? 1)),
            'status' => $status->value,
            'deleted_at' => $deletedAt,
        ]);

        $this->mapId(self::ENTITY_SERVICE_PACKAGE, $session->id, $packageId);
        $this->tally('package tiers');
    }

    private function migrateEvents(): void
    {
        $this->chunkLegacy('events', function (Collection $events): void {
            foreach ($events as $legacy) {
                if (! $this->hasCenter($legacy->barn_id, "Event #{$legacy->id}")) {
                    continue;
                }

                $serviceId = $this->mappedId(self::ENTITY_EVENT, $legacy->id);

                if ($serviceId !== null) {
                    $this->imageSources[$serviceId] = ['App\\Models\\Event', $legacy->id];
                    $this->tally('events already present');

                    continue;
                }

                $this->insertDetail(Event::class, $legacy, [
                    'occurrence_type' => $legacy->enter_type === 'specific_date' ? EventOccurrenceType::SpecificDay->value : EventOccurrenceType::General->value,
                    'start_date' => $legacy->start_date,
                    'end_date' => $legacy->end_date,
                    'open_date' => $legacy->open_date ?? $legacy->start_date,
                    'close_date' => $legacy->close_date ?? $legacy->end_date,
                    'max_tickets_per_day' => max(1, (int) $legacy->max_tickets),
                ]);

                $serviceId = $this->insertService(null, $legacy->barn_id, ServiceType::Event, Event::class, $legacy->id, $legacy);
                $this->mapId(self::ENTITY_EVENT, $legacy->id, $serviceId);

                $options = $this->legacy()->table('event_categories')->where('event_id', $legacy->id)->orderBy('id')->get()
                    ->map(fn (object $category): array => ['name' => Str::limit((string) $category->name, 255, ''), 'price' => $this->halalas($category->price), 'quantity' => null])
                    ->all();

                if ($options === []) {
                    $this->addWarning("Event #{$legacy->id}: no ticket categories.");
                }

                $this->insertOptions($serviceId, $options);
                $this->insertSettings($serviceId, ['enable_cash_on_arrival' => $this->cashOnArrival('App\\Models\\Event', $legacy->id)]);

                $this->imageSources[$serviceId] = ['App\\Models\\Event', $legacy->id];
                $this->tally('services: event');
            }
        });
    }

    /**
     * @param  class-string  $detailClass
     */
    private function migrateHorseOffers(string $table, string $detailClass, ServiceType $type, string $entity, string $legacyType): void
    {
        $this->chunkLegacy($table, function (Collection $offers) use ($detailClass, $type, $entity, $legacyType): void {
            foreach ($offers as $legacy) {
                if (! $this->hasCenter($legacy->barn_id, "{$type->value} #{$legacy->id}")) {
                    continue;
                }

                if ($this->mappedId($entity, $legacy->id) !== null) {
                    $this->tally("{$type->value} already present");

                    continue;
                }

                $this->insertDetail($detailClass, $legacy);
                $serviceId = $this->insertService(null, $legacy->barn_id, $type, $detailClass, $legacy->id, $legacy);
                $this->mapId($entity, $legacy->id, $serviceId);

                $options = $this->legacy()->table('service_addons')
                    ->where('addonable_type', $legacyType)
                    ->where('addonable_id', $legacy->id)
                    ->orderBy('id')
                    ->get()
                    ->map(fn (object $addon): array => ['name' => Str::limit((string) $addon->name, 255, ''), 'price' => $this->halalas($addon->price), 'quantity' => null])
                    ->all();

                if ($options === []) {
                    $this->addWarning("{$type->value} #{$legacy->id}: no priced options.");
                }

                $this->insertOptions($serviceId, $options);
                $this->tally("services: {$type->value}");
            }
        });
    }

    private function migrateMemberships(): void
    {
        $services = DB::table('services')->pluck('type', 'id');

        $this->chunkLegacy('memberships', function (Collection $memberships) use ($services): void {
            foreach ($memberships as $legacy) {
                if (! $this->hasCenter($legacy->barn_id, "Membership #{$legacy->id}")) {
                    continue;
                }

                $serviceId = $this->mappedId(self::ENTITY_MEMBERSHIP, $legacy->id);

                if ($serviceId !== null) {
                    $this->imageSources[$serviceId] = ['App\\Models\\Membership', $legacy->id];
                    $this->tally('memberships already present');

                    continue;
                }

                $visitServiceId = $services->get($legacy->visit_service_id) === ServiceType::Visit->value ? (int) $legacy->visit_service_id : null;

                if ($visitServiceId === null) {
                    $this->addWarning("Membership #{$legacy->id}: visit service #{$legacy->visit_service_id} was not migrated; membership has no visits.");
                }

                $color = '#'.ltrim((string) $legacy->color, '#');

                if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color) !== 1) {
                    $this->addWarning("Membership #{$legacy->id}: invalid color '{$legacy->color}'; stored as #000000.");
                    $color = '#000000';
                }

                $this->insertDetail(Membership::class, $legacy, [
                    'visit_service_id' => $visitServiceId,
                    'max_visits_per_day' => $visitServiceId !== null ? max(1, (int) $legacy->daily_invitation_limit) : null,
                    'duration' => max(1, (int) $legacy->duration),
                    'color' => strtoupper($color),
                ]);

                $serviceId = $this->insertService(null, $legacy->barn_id, ServiceType::Membership, Membership::class, $legacy->id, $legacy);
                $this->mapId(self::ENTITY_MEMBERSHIP, $legacy->id, $serviceId);
                $this->insertOptions($serviceId, [['name' => null, 'price' => $this->halalas($legacy->price), 'quantity' => null]]);
                $this->migrateMembershipPackages($legacy->id);

                $this->imageSources[$serviceId] = ['App\\Models\\Membership', $legacy->id];
                $this->tally('services: membership');
            }
        });
    }

    private function migrateMembershipPackages(int $membershipId): void
    {
        $rows = $this->legacy()->table('membership_packages')
            ->where('membership_id', $membershipId)
            ->whereNull('deleted_at')
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $packageId = $this->mappedId(self::ENTITY_SERVICE_PACKAGE, (int) $row->session_id);
            $package = $packageId === null ? null : DB::table('service_packages')->find($packageId);

            if ($package === null) {
                $this->addWarning("Membership #{$membershipId}: package tier #{$row->session_id} was not migrated; skipped.");

                continue;
            }

            $trainerId = $row->trainer_id;

            if ($trainerId !== null) {
                $trainingServiceId = DB::table('services')->where('id', $package->service_id)
                    ->where('type', ServiceType::Training->value)->value('serviceable_id');

                $attached = $trainingServiceId !== null && DB::table('trainer_training_service')
                    ->where('trainer_id', $trainerId)->where('training_service_id', $trainingServiceId)->exists();

                if (! $attached) {
                    $this->addWarning("Membership #{$membershipId}: trainer #{$trainerId} is not on the package's training service; trainer dropped.");
                    $trainerId = null;
                }
            }

            $this->tally('membership packages', DB::table('membership_packages')->insertOrIgnore([
                'membership_id' => $membershipId,
                'service_package_id' => $package->id,
                'price_option_id' => $package->price_option_id,
                'trainer_id' => $trainerId,
            ]));
        }
    }

    /**
     * @param  class-string  $detailClass
     * @param  array<string, mixed>  $columns
     */
    private function insertDetail(string $detailClass, object $legacy, array $columns = []): void
    {
        DB::table((new $detailClass)->getTable())->insert([
            'id' => $legacy->id,
            ...$columns,
            'deleted_at' => $legacy->deleted_at,
        ]);
    }

    /**
     * Inserts the `services` row (with $id, or auto-increment when null) and
     * returns its id.
     *
     * @param  class-string  $detailClass
     */
    private function insertService(?int $id, int $centerId, ServiceType $type, string $detailClass, int $detailId, object $legacy): int
    {
        $name = $this->translations($legacy->name);
        $description = $this->translations($legacy->description ?? null);
        $label = Str::headline($type->value)." #{$legacy->id}";

        $row = [
            'center_id' => $centerId,
            'name' => json_encode(array_filter(['ar' => $name['ar'] ?? $name['en'] ?? $label, 'en' => $name['en'] ?? null]), JSON_UNESCAPED_UNICODE),
            'slug' => $this->uniqueSlug($centerId, $name['en'] ?? $name['ar'] ?? null, $type, $legacy->id),
            'description' => json_encode(array_filter(['ar' => $description['ar'] ?? $description['en'] ?? '', 'en' => $description['en'] ?? null], fn (?string $value): bool => $value !== null), JSON_UNESCAPED_UNICODE),
            'status' => $legacy->status === ActivationStatus::INACTIVE->value ? ActivationStatus::INACTIVE->value : ActivationStatus::ACTIVE->value,
            'type' => $type->value,
            'serviceable_type' => (new $detailClass)->getMorphClass(),
            'serviceable_id' => $detailId,
            'created_at' => $legacy->created_at,
            'updated_at' => $legacy->updated_at,
            'deleted_at' => $legacy->deleted_at,
        ];

        if ($id !== null) {
            DB::table('services')->insert(['id' => $id, ...$row]);

            return $id;
        }

        return (int) DB::table('services')->insertGetId($row);
    }

    private function insertDurationOptions(int $serviceId, object $legacy): void
    {
        $options = $this->durationPrices($legacy)
            ->map(fn (array $entry): array => [
                'name' => null,
                'price' => $this->halalas($entry['price']),
                'quantity' => $entry['duration'],
                'unit' => PriceOptionUnit::MINUTE->value,
            ])
            ->all();

        if ($options === []) {
            $this->addWarning("Service #{$legacy->id} ({$legacy->service_type}): no duration prices.");
        }

        $this->insertOptions($serviceId, $options);
    }

    /**
     * @param  array<int, array{name: ?string, price: int, quantity: ?int, unit?: string}>  $options
     */
    private function insertOptions(int $serviceId, array $options): void
    {
        if ($options === []) {
            return;
        }

        DB::table('price_options')->insert(array_map(fn (array $option): array => [
            'service_id' => $serviceId,
            'name' => $option['name'],
            'price' => $option['price'],
            'quantity' => $option['quantity'],
            'unit' => $option['unit'] ?? PriceOptionUnit::OPTION->value,
        ], $options));

        $this->tally('price options', count($options));
    }

    /**
     * @param  class-string  $detailClass
     */
    private function insertSchedules(string $detailClass, int $detailId, mixed $days, mixed $hours, string $label): void
    {
        $windows = LegacySchedule::windows($hours);

        if (LegacySchedule::wrapsPastMidnight($windows)) {
            $this->addWarning("{$label}: hours run past midnight; the early-morning part is kept on the same day.");
        }

        $rows = LegacySchedule::scheduleRows((new $detailClass)->getMorphClass(), $detailId, LegacySchedule::days($days), $windows);

        if ($rows === []) {
            $this->addWarning("{$label}: no schedule (days or hours empty).");
        }

        DB::table('schedules')->insert($rows);
        $this->tally('schedule rows', count($rows));
    }

    /**
     * @param  array<string, bool>  $settings
     */
    private function insertSettings(int $serviceId, array $settings): void
    {
        DB::table('service_settings')->insert(collect($settings)
            ->map(fn (bool $value, string $key): array => ['service_id' => $serviceId, 'key' => $key, 'value' => $value ? '1' : '0'])
            ->values()
            ->all());
    }

    /**
     * Riding/training booking switches from the legacy reservable constraint.
     *
     * @return array<string, bool>
     */
    private function bookingSettings(string $legacyType, int $legacyId): array
    {
        $constraint = $this->constraints->get("{$legacyType}:{$legacyId}");

        return [
            'enable_cash_on_arrival' => (bool) ($constraint->enable_pay_on_arrival ?? false),
            'enable_single_session_booking' => (bool) ($constraint->enable_single_reservation ?? true),
            'enable_package_booking' => (bool) ($constraint->enable_package_reservation ?? true),
        ];
    }

    private function cashOnArrival(string $legacyType, int $legacyId): bool
    {
        return (bool) ($this->constraints->get("{$legacyType}:{$legacyId}")->enable_pay_on_arrival ?? false);
    }

    /**
     * Valid legacy `durations_prices` entries as [duration (minutes), price (SAR)].
     *
     * @return Collection<int, array{duration: int, price: float}>
     */
    private function durationPrices(object $legacy): Collection
    {
        $entries = json_decode((string) $legacy->durations_prices, true);

        return collect(is_array($entries) ? $entries : [])
            ->filter(fn (mixed $entry): bool => is_array($entry) && is_numeric($entry['duration'] ?? null) && (int) $entry['duration'] > 0 && is_numeric($entry['price'] ?? null))
            ->map(fn (array $entry): array => ['duration' => (int) $entry['duration'], 'price' => (float) $entry['price']])
            ->values();
    }

    /**
     * @return array{ar?: string, en?: string}
     */
    private function translations(mixed $json): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : null;

        if (! is_array($decoded)) {
            return is_string($json) && trim($json) !== '' ? ['ar' => $json] : [];
        }

        return collect(['ar' => $decoded['ar'] ?? null, 'en' => $decoded['en'] ?? null])
            ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
            ->all();
    }

    private function uniqueSlug(int $centerId, ?string $name, ServiceType $type, int $legacyId): string
    {
        $base = Str::limit(Str::slug((string) $name), 200, '') ?: Str::slug($type->value).'-'.$legacyId;
        $slug = $base;
        $counter = 1;

        while (isset($this->slugs[$centerId][$slug])) {
            $slug = $base.'-'.$counter++;
        }

        $this->slugs[$centerId][$slug] = true;

        return $slug;
    }

    private function hasCenter(int $barnId, string $label): bool
    {
        if ($this->centerIds->has($barnId)) {
            return true;
        }

        $this->addWarning("{$label}: barn #{$barnId} was not migrated; skipped.");

        return false;
    }
}
