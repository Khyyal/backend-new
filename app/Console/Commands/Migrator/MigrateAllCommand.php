<?php

namespace App\Console\Commands\Migrator;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

#[Signature('migrator:run
    {--dry-run : Run every migrator inside one transaction and roll it back}')]
#[Description('Run every legacy migrator in dependency order')]
class MigrateAllCommand extends LegacyMigrator
{
    /**
     * Migrators in dependency order. Each runs inside this command's
     * transaction, so a dry run rolls all of them back together.
     *
     * @var array<int, string>
     */
    private const MIGRATORS = [
        'migrator:cities',
        'migrator:centers',
        'migrator:tags',
        'migrator:center-users',
        'migrator:clients',
        'migrator:trainers',
        'migrator:services',
        'migrator:center-settings',
        'migrator:plans',
        'migrator:subscriptions',
        'migrator:client-memberships',
        'migrator:client-packages',
        'migrator:reservations',
        'migrator:event-reservations',
        'migrator:resort-reservations',
        'migrator:purchases',
    ];

    /** @var array<int, string> Migrators that copy media and accept --skip-media. */
    private const MEDIA_MIGRATORS = [
        'migrator:centers',
        'migrator:services',
    ];

    protected function migrate(): void
    {
        foreach (self::MIGRATORS as $migrator) {
            $arguments = [];

            if ($this->option('dry-run') && in_array($migrator, self::MEDIA_MIGRATORS, true)) {
                $arguments['--skip-media'] = true;
            }

            if ($this->option('dry-run') && $migrator === 'migrator:purchases') {
                $arguments['--skip-ledger'] = true;
            }

            $this->call($migrator, $arguments);
            $this->tally('migrators run');
        }
    }
}
