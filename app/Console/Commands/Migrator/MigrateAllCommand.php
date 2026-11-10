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
        'migrator:trainers',
        'migrator:services',
        'migrator:center-settings',
        'migrator:plans',
        'migrator:subscriptions',
    ];

    /** @var array<int, string> Migrators that copy media and accept --skip-media. */
    private const MEDIA_MIGRATORS = [
        'migrator:centers',
        'migrator:services',
    ];

    protected function migrate(): void
    {
        foreach (self::MIGRATORS as $migrator) {
            $arguments = in_array($migrator, self::MEDIA_MIGRATORS, true) && $this->option('dry-run')
                ? ['--skip-media' => true]
                : [];

            $this->call($migrator, $arguments);
            $this->tally('migrators run');
        }
    }
}
