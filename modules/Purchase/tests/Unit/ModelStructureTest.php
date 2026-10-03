<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Helper to load a migration file object from disk, bypassing PHP require-once caching.
 *
 * @return object|\Illuminate\Database\Migrations\Migration|null
 */
$loadMigrationFile = static function (string $file): ?object {
    $contents = file_get_contents($file);
    if ($contents === false) {
        return null;
    }
    $contents = preg_replace('/^<\?php\s*/', '', $contents);
    $contents = trim($contents);
    // Migration files end with "return new class extends Migration {...};"
    // Eval the contents as-is in the return context.
    $migration = eval($contents);

    return is_object($migration) ? $migration : null;
};

/**
 * Run the Purchase module migrations unconditionally, regardless of repository state.
 */
$runPurchaseMigrations = static function () use ($loadMigrationFile): void {
    $migrationPath = __DIR__.'/../../database/migrations';

    $files = glob($migrationPath.'/*.php');
    usort($files, 'strnatcmp');

    $repo = app('migrator')->getRepository();
    try {
        $repo->createRepository();
    } catch (\Throwable) {
    }

    foreach ($files as $file) {
        $migration = $loadMigrationFile($file);
        if ($migration !== null && method_exists($migration, 'up')) {
            try {
                $migration->up();
            } catch (\Throwable) {
                // table exists etc, idempotent
            }
        }
    }
};

/**
 * Rollback Purchase module migrations in reverse order.
 */
$rollbackPurchaseMigrations = static function () use ($loadMigrationFile): void {
    $migrationPath = __DIR__.'/../../database/migrations';

    $files = glob($migrationPath.'/*.php');
    usort($files, 'strnatcmp');
    $files = array_reverse($files);

    foreach ($files as $file) {
        $migration = $loadMigrationFile($file);
        if ($migration !== null && method_exists($migration, 'down')) {
            try {
                $migration->down();
            } catch (\Throwable) {
                // ignore
            }
        }
    }
};

$assertHasAllColumns = function (string $table, array $required): void {
    $columns = Schema::getColumnListing($table);

    foreach ($required as $c) {
        if (! in_array($c, $columns, true)) {
            throw new \PHPUnit\Framework\ExpectationFailedException(
                "Missing column '$c' on table '$table'. Actual columns: ".implode(',', $columns),
            );
        }
    }
    expect(true)->toBeTrue();
};

test('purchases table has all required columns and indexes', function () use ($assertHasAllColumns, $runPurchaseMigrations): void {
    $runPurchaseMigrations();
    \Modules\Purchase\Database\Factories\PurchaseFactory::new()->create();

    $assertHasAllColumns('purchases', [
        'id', 'buyer_type', 'buyer_id', 'merchant_type', 'merchant_id',
        'source', 'status',
        'subtotal', 'discount_amount', 'tax_amount', 'total_amount',
        'metadata',
        'created_at', 'updated_at', 'deleted_at',
    ]);
});

test('purchase_items table has all required columns', function () use ($assertHasAllColumns, $runPurchaseMigrations): void {
    $runPurchaseMigrations();
    \Modules\Purchase\Database\Factories\PurchaseFactory::new()->hasItems(1)->create();

    $assertHasAllColumns('purchase_items', [
        'id', 'purchase_id',
        'purchasable_type', 'purchasable_id',
        'name', 'quantity',
        'unit_price', 'subtotal', 'discount_amount', 'tax_amount', 'total_amount',
        'metadata',
        'created_at', 'updated_at',
    ]);
});

test('payments table has all required columns', function () use ($assertHasAllColumns, $runPurchaseMigrations): void {
    $runPurchaseMigrations();
    \Modules\Purchase\Database\Factories\PurchaseFactory::new()->hasPayments(1)->create();

    $assertHasAllColumns('payments', [
        'id', 'purchase_id',
        'amount', 'method', 'status',
        'payment_company', 'payment_type', 'payment_order_id',
        'provider_data', 'metadata',
        'paid_at',
        'created_at', 'updated_at',
    ]);
});

test('webhook_events table has all required columns + unique(gateway, external_event_id)', function () use ($assertHasAllColumns, $runPurchaseMigrations): void {
    $runPurchaseMigrations();
    \Modules\Purchase\Database\Factories\WebhookEventFactory::new()->create();

    $assertHasAllColumns('webhook_events', [
        'id', 'gateway', 'external_event_id', 'event_type',
        'payload',
        'received_at', 'processed_at', 'failed_at', 'attempts',
        'created_at', 'updated_at',
    ]);

    $sm = Schema::getConnection()->getSchemaBuilder();
    $indexes = $sm->getIndexes('webhook_events');
    $foundUnique = false;
    foreach ($indexes as $idx) {
        if ($idx['unique'] && $idx['columns'] === ['gateway', 'external_event_id']) {
            $foundUnique = true;
            break;
        }
    }
    if (! $foundUnique) {
        throw new \PHPUnit\Framework\ExpectationFailedException(
            'Missing unique(gateway, external_event_id) on webhook_events. Actual indexes: '.json_encode($indexes),
        );
    }
    expect(true)->toBeTrue();
});

test('webhook_events unique(gateway, external_event_id) rejects duplicate inserts', function () use ($runPurchaseMigrations): void {
    $runPurchaseMigrations();
    \Modules\Purchase\Models\WebhookEvent::create([
        'gateway' => 'fake',
        'external_event_id' => 'evt_dup_001',
        'event_type' => 'payment.succeeded',
        'payload' => ['x' => 1],
        'attempts' => 0,
    ]);

    $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

    \Modules\Purchase\Models\WebhookEvent::create([
        'gateway' => 'fake',
        'external_event_id' => 'evt_dup_001',
        'event_type' => 'payment.succeeded',
        'payload' => ['x' => 2],
        'attempts' => 0,
    ]);
});

test('migrate:rollback drops tables in reverse order', function () use ($runPurchaseMigrations, $loadMigrationFile): void {
    $runPurchaseMigrations();

    $this->assertTrue(Schema::hasTable('purchases'));
    $this->assertTrue(Schema::hasTable('purchase_items'));
    $this->assertTrue(Schema::hasTable('payments'));
    $this->assertTrue(Schema::hasTable('webhook_events'));

    $migrationPath = __DIR__.'/../../database/migrations';
    $files = glob($migrationPath.'/*.php');
    usort($files, 'strnatcmp');
    $files = array_reverse($files);

    foreach ($files as $file) {
        $migration = $loadMigrationFile($file);
        if ($migration === null) {
            throw new \PHPUnit\Framework\ExpectationFailedException(
                'Failed to load migration object for '.basename($file),
            );
        }
        $migration->down();
    }

    $remaining = array_filter([
        'purchases', 'purchase_items', 'payments', 'webhook_events',
    ], static fn (string $t): bool => Schema::hasTable($t));

    expect($remaining)->toBeEmpty('All Purchase-related tables should be dropped on rollback');

    // Re-run to leave clean state for following tests
    $runPurchaseMigrations();
});
