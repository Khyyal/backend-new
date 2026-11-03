<?php

use Illuminate\Support\Facades\Route;
use Modules\Notifications\Http\Controllers\Admin\NotificationCampaignController;
use Modules\Notifications\Http\Controllers\Admin\WhatsAppTemplateController;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth:system_user'])
    ->group(function (): void {
        Route::apiResource('whatsapp-templates', WhatsAppTemplateController::class)
            ->parameters(['whatsapp-templates' => 'whatsapp_template']);

        Route::apiResource('notification-campaigns', NotificationCampaignController::class)
            ->parameters(['notification-campaigns' => 'notification_campaign']);

        Route::prefix('notification-campaigns/{notification_campaign}')
            ->name('notification-campaigns.')
            ->group(function (): void {
                Route::post('/send', [NotificationCampaignController::class, 'send'])->name('send');
                Route::post('/schedule', [NotificationCampaignController::class, 'schedule'])->name('schedule');
                Route::post('/duplicate', [NotificationCampaignController::class, 'duplicate'])->name('duplicate');
                Route::post('/cancel', [NotificationCampaignController::class, 'cancel'])->name('cancel');
                Route::get('/deliveries', [NotificationCampaignController::class, 'deliveries'])->name('deliveries');
            });
    });
