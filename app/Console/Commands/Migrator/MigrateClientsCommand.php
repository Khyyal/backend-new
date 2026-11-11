<?php

namespace App\Console\Commands\Migrator;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Clients\Models\Client;
use Modules\Support\Enums\Gender;

#[Signature('migrator:clients {--dry-run : Run inside a transaction and roll it back}')]
#[Description('Copy legacy clients (merging duplicate phones), their registering center and their push tokens as devices')]
class MigrateClientsCommand extends LegacyMigrator
{
    public const ENTITY = 'client';

    private const COLUMNS = ['id', 'first_name', 'last_name', 'email', 'phone_number', 'sub', 'gender', 'image', 'fcm_token', 'device_token', 'lang', 'city_id', 'registered_barn_id', 'created_at', 'updated_at', 'deleted_at'];

    protected function migrate(): void
    {
        $clients = $this->legacy()->table('clients')->orderBy('id')->get(self::COLUMNS);
        $canonical = $this->migrateClients($clients);

        $this->migrateCenterLinks($clients, $canonical);
        $this->attachDevices((new Client)->getMorphClass(), $clients
            ->filter(fn (object $client): bool => isset($canonical[$client->id]))
            ->map(fn (object $client): array => [
                'owner_id' => $canonical[$client->id],
                'token' => $client->fcm_token,
                'identifier' => $client->device_token,
                'locale' => $client->lang,
                'seen_at' => $client->updated_at,
            ])
            ->values());

        $this->reportDropped($clients);
    }

    /**
     * Inserts one client per phone, keeping the legacy id. Clients sharing a
     * phone are folded into the non-deleted, most recently updated one and
     * recorded in legacy_id_maps. Clients without a phone are never merged.
     *
     * @param  Collection<int, object>  $clients
     * @return array<int, int> legacy client id → new client id
     */
    private function migrateClients(Collection $clients): array
    {
        $canonical = [];
        $cityIds = DB::table('cities')->pluck('id')->flip();
        $existingIds = DB::table('clients')->pluck('id')->flip();
        $existingByPhone = DB::table('clients')->where('phone_number', '!=', '')->pluck('id', 'phone_number');
        $rows = [];

        $groups = $clients->groupBy(fn (object $client): string => blank($client->phone_number) ? "none-{$client->id}" : trim($client->phone_number));

        foreach ($groups as $group) {
            $keeper = $group->sortBy([
                fn (object $a, object $b): int => ($a->deleted_at !== null) <=> ($b->deleted_at !== null),
                fn (object $a, object $b): int => strcmp((string) $b->updated_at, (string) $a->updated_at),
                fn (object $a, object $b): int => $b->id <=> $a->id,
            ])->first();

            $phone = trim((string) $keeper->phone_number);
            $phoneOwner = $phone === '' ? null : $existingByPhone->get($phone);
            $clientId = $keeper->id;

            if ($phoneOwner !== null && ! $group->pluck('id')->contains($phoneOwner)) {
                $this->addWarning("Client #{$keeper->id}: phone {$phone} already belongs to client #{$phoneOwner}; merged into it.");
                $clientId = (int) $phoneOwner;
            } elseif ($existingIds->has($keeper->id)) {
                $this->tally('clients already present');
            } else {
                $rows[] = [
                    'id' => $keeper->id,
                    'first_name' => blank($keeper->first_name) ? null : trim($keeper->first_name),
                    'last_name' => blank($keeper->last_name) ? null : trim($keeper->last_name),
                    'phone_number' => $phone,
                    'gender' => (Gender::tryFrom((string) $keeper->gender) ?? Gender::OTHER)->value,
                    'city_id' => $cityIds->has($keeper->city_id) ? $keeper->city_id : null,
                    'deleted_at' => $keeper->deleted_at,
                    'created_at' => $keeper->created_at,
                    'updated_at' => $keeper->updated_at,
                ];

                $this->tally('clients');

                if ($phone === '') {
                    $this->tally('clients without phone');
                }
            }

            foreach ($group as $client) {
                $canonical[$client->id] = $clientId;

                if ($client->id !== $clientId) {
                    $this->mapId(self::ENTITY, $client->id, $clientId);
                    $this->tally('clients merged (duplicate phone)');
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('clients')->insert($chunk);
        }

        if (($this->stats['clients without phone'] ?? 0) > 0) {
            $this->addWarning("{$this->stats['clients without phone']} clients have no phone number (social sign-in); migrated with an empty phone, so they cannot log in.");
        }

        return $canonical;
    }

    /**
     * `registered_barn_id` → `center_client`, for migrated centers only.
     *
     * @param  Collection<int, object>  $clients
     * @param  array<int, int>  $canonical
     */
    private function migrateCenterLinks(Collection $clients, array $canonical): void
    {
        $centerIds = DB::table('centers')->pluck('id')->flip();

        $rows = $clients
            ->filter(fn (object $client): bool => $client->registered_barn_id !== null && $centerIds->has($client->registered_barn_id))
            ->map(fn (object $client): array => [
                'center_id' => $client->registered_barn_id,
                'client_id' => $canonical[$client->id],
                'created_at' => $client->created_at,
                'updated_at' => $client->created_at,
            ])
            ->unique(fn (array $row): string => "{$row['center_id']}-{$row['client_id']}")
            ->values()
            ->all();

        if ($rows !== []) {
            $this->tally('center client links added', DB::table('center_client')->insertOrIgnore($rows));
        }
    }

    /**
     * @param  Collection<int, object>  $clients
     */
    private function reportDropped(Collection $clients): void
    {
        $dropped = collect(['email', 'sub', 'image'])
            ->mapWithKeys(fn (string $column): array => [$column => $clients->filter(fn (object $client): bool => filled($client->{$column}))->count()])
            ->filter()
            ->map(fn (int $count, string $column): string => "{$column} ({$count})");

        if ($dropped->isNotEmpty()) {
            $this->addWarning('Client fields with no new column were dropped: '.$dropped->implode(', ').'.');
        }
    }
}
