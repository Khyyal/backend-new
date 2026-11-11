<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Points the `legacy` connection at a fresh in-memory sqlite database and
 * creates the legacy tables (minimal columns) that the service migrators read.
 */
function useLegacyServiceSchema(): void
{
    config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::purge('legacy');

    createLegacyServiceTables();
    createLegacyBillingTables();
    createLegacyClientTables();
    createLegacyBookingTables();
    createLegacyEngagementTables();
}

/**
 * Creates the legacy service tables on the current `legacy` connection,
 * skipping any table a test already created with its own columns.
 */
function createLegacyServiceTables(): void
{
    $base = Schema::connection('legacy');
    $schema = new class($base)
    {
        public function __construct(private Builder $schema) {}

        public function create(string $table, Closure $callback): void
        {
            if (! $this->schema->hasTable($table)) {
                $this->schema->create($table, $callback);
            }
        }
    };
    $translatable = fn (Blueprint $table) => [$table->text('name'), $table->text('description')->nullable()];
    $lifecycle = function (Blueprint $table): void {
        $table->string('status')->default('active');
        $table->timestamps();
        $table->softDeletes();
    };

    $schema->create('trainers', function (Blueprint $table) use ($lifecycle): void {
        $table->id();
        $table->string('name');
        $table->unsignedBigInteger('barn_id');
        $table->text('days');
        $table->text('hours')->nullable();
        $table->integer('max_tickets_per_hour')->nullable();
        $table->unsignedBigInteger('barn_manager_id')->nullable();
        $lifecycle($table);
    });

    $schema->create('services', function (Blueprint $table) use ($translatable, $lifecycle): void {
        $table->id();
        $table->unsignedBigInteger('barn_id');
        $table->string('service_type');
        $table->text('durations_prices')->nullable();
        $table->integer('max_tickets_per_hour')->nullable();
        $table->integer('max_tickets_per_day')->nullable();
        $table->double('tickets_price')->nullable();
        $table->text('days')->nullable();
        $table->text('hours')->nullable();
        $table->string('visit_type')->nullable();
        $translatable($table);
        $lifecycle($table);
    });

    $schema->create('resort_days', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('service_id');
        $table->string('day');
        $table->double('price');
    });

    $schema->create('resort_additional_services', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('service_id');
        $table->string('name');
        $table->double('price');
    });

    $schema->create('resort_services_settings', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('service_id');
        foreach (['close_all_free_riding', 'close_all_training', 'close_all_resort', 'close_all_visit'] as $flag) {
            $table->boolean($flag)->default(true);
        }
    });

    $schema->create('events', function (Blueprint $table) use ($translatable, $lifecycle): void {
        $table->id();
        $table->unsignedBigInteger('barn_id');
        $table->date('start_date');
        $table->date('end_date');
        $table->date('open_date')->nullable();
        $table->date('close_date')->nullable();
        $table->integer('max_tickets')->default(0);
        $table->string('enter_type')->nullable();
        $translatable($table);
        $lifecycle($table);
    });

    $schema->create('event_categories', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('event_id');
        $table->string('name');
        $table->double('price');
    });

    foreach (['horse_services', 'horse_cares'] as $offers) {
        $schema->create($offers, function (Blueprint $table) use ($translatable, $lifecycle): void {
            $table->id();
            $table->unsignedBigInteger('barn_id');
            $translatable($table);
            $lifecycle($table);
        });
    }

    $schema->create('service_addons', function (Blueprint $table): void {
        $table->id();
        $table->string('addonable_type');
        $table->unsignedBigInteger('addonable_id');
        $table->decimal('price', 8, 2);
        $table->string('name');
    });

    $schema->create('memberships', function (Blueprint $table) use ($translatable, $lifecycle): void {
        $table->id();
        $table->unsignedBigInteger('barn_id');
        $table->unsignedBigInteger('visit_service_id');
        $table->double('price');
        $table->integer('duration');
        $table->integer('daily_invitation_limit');
        $table->string('color');
        $translatable($table);
        $lifecycle($table);
    });

    $schema->create('packages', function (Blueprint $table) use ($lifecycle): void {
        $table->id();
        $table->unsignedBigInteger('service_id')->nullable();
        $table->integer('duration')->nullable();
        $lifecycle($table);
    });

    $schema->create('package_sessions', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('package_id');
        $table->integer('count');
        $table->double('price');
        $table->integer('duration')->nullable();
        $table->softDeletes();
    });

    $schema->create('membership_packages', function (Blueprint $table): void {
        $table->id();
        $table->integer('order')->default(0);
        $table->unsignedBigInteger('package_id');
        $table->unsignedBigInteger('membership_id');
        $table->unsignedBigInteger('session_id');
        $table->unsignedBigInteger('trainer_id')->nullable();
        $table->softDeletes();
    });

    $schema->create('trainer_service', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('trainer_id');
        $table->unsignedBigInteger('service_id');
    });

    $schema->create('trainer_package', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('trainer_id');
        $table->unsignedBigInteger('package_id');
    });

    $schema->create('reservable_constraints', function (Blueprint $table): void {
        $table->id();
        $table->string('constrainable_type');
        $table->unsignedBigInteger('constrainable_id');
        $table->boolean('enable_pay_on_arrival')->default(false);
        $table->boolean('enable_single_reservation')->nullable();
        $table->boolean('enable_package_reservation')->nullable();
    });

    $schema->create('images', function (Blueprint $table): void {
        $table->id();
        $table->string('filename');
        $table->string('imageable_type')->nullable();
        $table->unsignedBigInteger('imageable_id')->nullable();
        $table->string('blur_hash')->nullable();
        $table->integer('order')->default(0);
    });

    $schema->create('custom_working_hours', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('barn_id');
        $table->text('days');
        $table->text('hours')->nullable();
        $table->string('gender');
    });

    $schema->create('service_policies', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('barn_id');
        $table->string('service_type');
        $table->text('policy');
        $table->timestamps();
    });
}

/**
 * JSON-encodes a legacy translatable value.
 */
function legacyName(string $ar, ?string $en = null): string
{
    return json_encode(['ar' => $ar, 'en' => $en], JSON_UNESCAPED_UNICODE);
}

/**
 * Creates the legacy tag (barn features) and billing tables on the current
 * `legacy` connection, skipping any table that already exists.
 */
function createLegacyBillingTables(): void
{
    $schema = Schema::connection('legacy');

    if (! $schema->hasTable('features')) {
        $schema->create('features', function (Blueprint $table): void {
            $table->id();
            $table->text('name');
            $table->string('image')->nullable();
            $table->timestamps();
        });
    }

    if (! $schema->hasTable('barn_features')) {
        $schema->create('barn_features', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('barn_id');
            $table->unsignedBigInteger('feature_id');
            $table->timestamps();
        });
    }

    if (! $schema->hasTable('account_plans')) {
        $schema->create('account_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('features')->nullable();
            $table->decimal('monthly_price', 8, 2)->default(0);
            $table->decimal('pre_discount_price', 8, 2)->nullable();
            $table->decimal('yearly_price', 8, 2)->default(0);
            $table->integer('sort_order')->default(0);
            $table->string('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    if (! $schema->hasTable('subscriptions')) {
        $schema->create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('barn_id');
            $table->unsignedBigInteger('account_plan_id');
            $table->text('plan_name');
            $table->double('price_at_purchase');
            $table->string('status');
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->timestamps();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('billing_cycle');
        });
    }
}

/**
 * Creates the legacy client and manager push-token tables on the current
 * `legacy` connection, skipping any table that already exists.
 */
function createLegacyClientTables(): void
{
    $schema = Schema::connection('legacy');

    if (! $schema->hasTable('clients')) {
        $schema->create('clients', function (Blueprint $table): void {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('sub')->nullable();
            $table->string('gender')->nullable()->default('unknown');
            $table->string('image')->nullable();
            $table->string('fcm_token')->nullable();
            $table->string('device_token')->nullable();
            $table->string('lang')->default('ar');
            $table->unsignedBigInteger('city_id')->nullable();
            $table->unsignedBigInteger('registered_barn_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    if (! $schema->hasTable('manager_fcm_tokens')) {
        $schema->create('manager_fcm_tokens', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('manager_id');
            $table->string('fcm_token')->nullable();
            $table->timestamps();
        });
    }
}

/**
 * Creates the legacy booking and purchase tables on the current `legacy`
 * connection, skipping any table that already exists.
 */
function createLegacyBookingTables(): void
{
    $schema = Schema::connection('legacy');
    $tables = [
        'purchases' => function (Blueprint $table): void {
            $table->id();
            $table->string('status')->default('pending');
            $table->boolean('pay_when_arrived')->default(false);
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->string('order_id')->nullable();
            $table->string('coupon_code')->nullable();
            $table->decimal('discount', 10, 2)->nullable();
            $table->decimal('price_after_discount', 10, 2)->nullable();
            $table->decimal('total_price_tax_added', 10, 2)->nullable();
            $table->decimal('tax_amount', 10, 2)->nullable();
            $table->string('service_type')->nullable();
            $table->string('name')->nullable();
            $table->double('total_price')->nullable();
            $table->string('code');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->string('purchasable_type')->nullable();
            $table->unsignedBigInteger('purchasable_id')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('barn_id')->nullable();
            $table->string('card_type')->nullable();
            $table->text('cancel_note')->nullable();
            $table->string('created_by_type')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->string('refund_note')->nullable();
            $table->text('payment_information')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->string('channel')->default('mobile');
            $table->timestamps();
        },
        'reservations' => function (Blueprint $table): void {
            $table->id();
            $table->string('code');
            $table->date('day');
            $table->time('time')->nullable();
            $table->integer('tickets_booked')->default(1);
            $table->string('status')->default('active');
            $table->integer('duration')->nullable();
            $table->double('ticket_price')->nullable();
            $table->string('service_type')->nullable();
            $table->unsignedBigInteger('clients_sessions_id')->nullable();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->unsignedBigInteger('trainer_id')->nullable();
            $table->unsignedBigInteger('purchase_id')->nullable();
            $table->timestamps();
        },
        'clients_sessions' => function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('session_id');
            $table->integer('count');
            $table->dateTime('end_date')->nullable();
            $table->integer('duration')->nullable();
            $table->unsignedBigInteger('trainer_id')->nullable();
            $table->integer('reserved_count')->default(0);
            $table->string('status')->nullable();
            $table->unsignedBigInteger('membership_reservation_id')->nullable();
            $table->unsignedBigInteger('purchase_id')->nullable();
            $table->string('knight_name')->nullable();
            $table->boolean('controlled')->default(false);
            $table->timestamps();
        },
        'event_reservations' => function (Blueprint $table): void {
            $table->id();
            $table->text('category');
            $table->integer('tickets');
            $table->date('date')->nullable();
            $table->unsignedBigInteger('event_id');
            $table->string('status')->nullable();
            $table->timestamps();
        },
        'resort_reservations' => function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('service_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('additional_services')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        },
        'membership_reservations' => function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('membership_id');
            $table->string('status')->default('pending');
            $table->date('end_date');
            $table->integer('daily_invitation_limit');
            $table->timestamps();
        },
        'purchase_financial_snapshots' => function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('purchase_id');
            $table->decimal('purchase_amount', 12, 2);
            $table->decimal('gateway_fee', 12, 2);
            $table->decimal('platform_commission_percentage', 5, 2);
            $table->decimal('platform_share', 12, 2);
            $table->decimal('barn_share', 12, 2);
            $table->timestamps();
        },
    ];

    foreach ($tables as $name => $callback) {
        if (! $schema->hasTable($name)) {
            $schema->create($name, $callback);
        }
    }
}

/**
 * Creates the legacy `action_logs` table on the current `legacy` connection,
 * unless it already exists.
 */
function createLegacyEngagementTables(): void
{
    $schema = Schema::connection('legacy');

    if (! $schema->hasTable('action_logs')) {
        $schema->create('action_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->string('action');
            $table->timestamps();
        });
    }
}
