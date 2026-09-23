<?php

namespace Modules\Clients\Services;

use Illuminate\Http\Request;
use Modules\Clients\Models\Client;

class ClientAuthService
{
    public function findOrCreateByPhone(string $phoneNumber): Client
    {
        return Client::query()->firstOrCreate([
            'phone_number' => $phoneNumber,
        ]);
    }


    public function setClientName(Client $client, string $firstName,string $lastName): Client
    {
        $client->forceFill(['first_name' => $firstName, 'last_name' => $lastName])->save();

        return $client->fresh() ?? $client;
    }

    public function issueAppToken(Client $client, ?Request $request = null, ?string $deviceName = null): string
    {
        $fallbackName = $request?->userAgent() ?? 'app-token';

        return $client->createToken($deviceName ?? $fallbackName)->plainTextToken;
    }
}
