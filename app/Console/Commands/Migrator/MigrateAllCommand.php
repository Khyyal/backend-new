<?php

namespace App\Console\Commands\Migrator;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;

#[Signature('migrator:run
    {--dry-run : Run every migrator inside one transaction and roll it back}
    {--media-base= : Passed to migrator:centers to attach legacy logos}')]
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
        'migrator:center-users',
    ];

    protected function migrate(): void
    {
        foreach (self::MIGRATORS as $migrator) {
            $arguments = $migrator === 'migrator:centers' && ! $this->option('dry-run')
                ? ['--media-base' => $this->option('media-base')]
                : [];

            $this->call($migrator, $arguments);
            $this->tally('migrators run');
        }
    }
}
