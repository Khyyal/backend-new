<?php

namespace Modules\Clients\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Centers\Models\Center;
use Modules\Clients\Events\ClientRegistered;
use Modules\Clients\Models\Client;

/**
 * Clients handled by a center at the desk: exact phone lookup across all
 * clients, and creation of a client who has no account yet. A created client
 * belongs to the center (center_client) and can later sign in by OTP with
 * the same phone.
 */
class CenterClientService
{
    public function findByPhone(string $phoneNumber): ?Client
    {
        return Client::query()
            ->where('phone_number', $phoneNumber)
            ->oldest('id')
            ->first();
    }

    /**
     * @param  array{phone_number: string, first_name: string, last_name: string}  $data
     *
     * @throws ValidationException When the phone already belongs to a client.
     */
    public function create(Center $center, array $data): Client
    {
        return DB::transaction(function () use ($center, $data): Client {
            // `clients.phone_number` has no unique index, so the check runs under lock.
            $taken = Client::query()
                ->where('phone_number', $data['phone_number'])
                ->lockForUpdate()
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages([
                    'phone_number' => [__('validation.phone_taken')],
                ]);
            }

            $client = new Client;
            $client->forceFill([
                'phone_number' => $data['phone_number'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
            ])->save();

            $center->clients()->syncWithoutDetaching([$client->getKey()]);

            DB::afterCommit(fn () => event(new ClientRegistered($client)));

            return $client;
        });
    }
}
