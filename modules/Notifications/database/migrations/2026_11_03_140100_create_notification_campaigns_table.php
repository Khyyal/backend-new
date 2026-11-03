<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('channel', 20);
            $table->string('audience', 20);
            $table->string('target_mode', 20)->default('all');
            $table->string('status', 20)->default('draft');

            $table->json('title')->nullable();
            $table->json('body')->nullable();
            $table->string('action_type', 20)->default('none');
            $table->unsignedBigInteger('action_target_id')->nullable();
            $table->string('action_url', 2048)->nullable();

            $table->foreignId('whatsapp_template_id')->nullable()->constrained('whatsapp_templates')->restrictOnDelete();
            $table->json('template_parameters')->nullable();

            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('system_users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'scheduled_at']);
        });

        Schema::create('notification_campaign_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_campaign_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('target_id');

            $table->unique(['notification_campaign_id', 'target_id'], 'notification_campaign_target_unique');
        });

        Schema::create('notification_campaign_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_campaign_id')
                ->constrained(indexName: 'campaign_delivery_campaign_foreign')
                ->cascadeOnDelete();
            $table->morphs('recipient', 'campaign_delivery_recipient_index');
            $table->string('phone', 32)->nullable();
            $table->string('status', 20);
            $table->string('provider_message_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['notification_campaign_id', 'recipient_type', 'recipient_id'], 'notification_campaign_delivery_unique');
            $table->index(['notification_campaign_id', 'status'], 'campaign_delivery_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_campaign_deliveries');
        Schema::dropIfExists('notification_campaign_targets');
        Schema::dropIfExists('notification_campaigns');
    }
};
