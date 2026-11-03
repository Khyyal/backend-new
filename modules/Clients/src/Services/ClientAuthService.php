<?php

namespace Modules\Clients\Services;

use Illuminate\Http\Request;
use Modules\Clients\Events\ClientRegistered;
use Modules\Clients\Models\Client;

class ClientAuthService
{
    public function findOrCreateByPhone(string $phoneNumber): Client
    {
        $client = Client::query()->firstOrCreate([
            'phone_number' => $phoneNumber,
        ]);

        if ($client->wasRecentlyCreated) {
            event(new ClientRegistered($client));
        }

        return $client;
    }

    public function setClientName(Client $client, string $firstName, string $lastName): Client
    {
        $client->forceFill(['first_name' => $firstName, 'last_name' => $lastName])->save();

        return $client->fresh() ?? $client;
    }

    public function updateProfile(Client $client, array $data): Client
    {
        $attributes = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
        ];

        if (array_key_exists('city_id', $data)) {
            $attributes['city_id'] = $data['city_id'];
        }

        $client->forceFill($attributes)->save();

        return $client->fresh() ?? $client;
    }

    public function issueAppToken(Client $client, ?Request $request = null, ?string $deviceName = null): string
    {
        $fallbackName = $request?->userAgent() ?? 'app-token';

        return $client->createToken($deviceName ?? $fallbackName)->plainTextToken;
    }
}
