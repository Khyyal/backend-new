<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Settlements\Database\Factories\CenterLedgerEntryFactory;
use Modules\Settlements\Database\Factories\CenterPayoutFactory;
use Modules\Settlements\Enums\CenterPayoutStatus;
use Modules\Settlements\Models\PayoutInvoiceTemplate;
use Modules\Support\Database\Factories\SystemUserFactory;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Services\Media\TemporaryMediaService;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');

    $this->admin = SystemUserFactory::new()->withPermissions([SystemPermission::ManagePayouts])->create();
    $this->payout = CenterPayoutFactory::new()->create(['amount' => 9000, 'entries_count' => 1]);
    CenterLedgerEntryFactory::new()->create([
        'center_id' => $this->payout->center_id,
        'payout_id' => $this->payout->id,
        'amount' => 9000,
    ]);

    $this->payload = fn (array $overrides = []): array => [
        'company_name' => ['en' => 'Khyyal Co', 'ar' => 'شركة خيال'],
        'company_address' => ['en' => 'Riyadh', 'ar' => 'الرياض'],
        'tax_number' => '300000000000003',
        'accent_color' => '#aa3300',
        'header_note' => ['en' => '<p>Weekly settlement</p>', 'ar' => '<p>تسوية أسبوعية</p>'],
        'footer_note' => ['en' => '<p>Thanks</p><script>alert(1)</script>', 'ar' => null],
        'show_bank_details' => true,
        'show_entry_summary' => true,
        'show_entries_table' => false,
        'show_signature' => true,
        ...$overrides,
    ];
});

test('admins print a payout invoice as a PDF in each locale', function (string $locale): void {
    $response = $this->actingAs($this->admin, 'system_user')
        ->get("/api/v1/admin/payouts/{$this->payout->id}/invoice?locale={$locale}");

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', "inline; filename=\"payout-invoice-{$this->payout->id}-{$locale}.pdf\"");
    expect($response->getContent())->toStartWith('%PDF');
})->with(['ar', 'en']);

test('a cancelled payout still prints', function (): void {
    $this->payout->update(['status' => CenterPayoutStatus::Cancelled, 'cancelled_at' => now()]);

    $response = $this->actingAs($this->admin, 'system_user')->get("/api/v1/admin/payouts/{$this->payout->id}/invoice");

    $response->assertOk();
    expect($response->getContent())->toStartWith('%PDF');
});

test('printing rejects an unknown locale', function (): void {
    $this->actingAs($this->admin, 'system_user')
        ->getJson("/api/v1/admin/payouts/{$this->payout->id}/invoice?locale=fr")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['locale' => 'The selected locale is invalid.']);
});

test('printing an unknown payout is not found', function (): void {
    $this->actingAs($this->admin, 'system_user')->getJson('/api/v1/admin/payouts/999999/invoice')->assertNotFound();
});

test('invoice endpoints need payouts.manage and an active account', function (string $method, string $uri): void {
    $uri = str_replace('{payout}', (string) $this->payout->id, $uri);
    $body = $method === 'get' ? [] : [...($this->payload)(), 'locale' => 'en'];

    $withoutPermission = SystemUserFactory::new()->create();
    $this->actingAs($withoutPermission, 'system_user')->json($method, $uri, $body)->assertForbidden();

    $inactive = SystemUserFactory::new()->inactive()->withPermissions([SystemPermission::ManagePayouts])->create();
    $this->actingAs($inactive, 'system_user')->json($method, $uri, $body)->assertForbidden();
})->with([
    'print' => ['get', '/api/v1/admin/payouts/{payout}/invoice'],
    'show template' => ['get', '/api/v1/admin/payout-invoice-template'],
    'update template' => ['put', '/api/v1/admin/payout-invoice-template'],
    'preview template' => ['post', '/api/v1/admin/payout-invoice-template/preview'],
]);

test('invoice endpoints need authentication', function (): void {
    $this->getJson("/api/v1/admin/payouts/{$this->payout->id}/invoice")->assertUnauthorized();
});

test('admins update the template, with notes sanitized and cleared locales removed', function (): void {
    $template = PayoutInvoiceTemplate::current();
    $template->replaceTranslations('footer_note', ['en' => '<p>Old</p>', 'ar' => '<p>قديم</p>'])->save();

    $this->actingAs($this->admin, 'system_user')
        ->putJson('/api/v1/admin/payout-invoice-template', ($this->payload)())
        ->assertOk()
        ->assertJsonPath('data.company_name.ar', 'شركة خيال')
        ->assertJsonPath('data.accent_color', '#AA3300')
        ->assertJsonPath('data.footer_note', ['en' => '<p>Thanks</p>', 'ar' => null])
        ->assertJsonPath('data.show_entries_table', false)
        ->assertJsonPath('data.logo', null);

    $template->refresh();
    expect($template->getTranslation('footer_note', 'ar', false))->toBe('')
        ->and($template->tax_number)->toBe('300000000000003')
        ->and($template->show_entries_table)->toBeFalse();
});

test('the template rejects an invalid accent color', function (): void {
    $this->actingAs($this->admin, 'system_user')
        ->putJson('/api/v1/admin/payout-invoice-template', ($this->payload)(['accent_color' => 'red']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['accent_color' => 'The accent color field format is invalid.']);
});

test('the template requires the company name in both locales', function (): void {
    $this->actingAs($this->admin, 'system_user')
        ->putJson('/api/v1/admin/payout-invoice-template', ($this->payload)(['company_name' => ['en' => 'Khyyal']]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['company_name.ar']);
});

test('admins set and remove the logo', function (): void {
    $uuid = app(TemporaryMediaService::class)->createTemporary(UploadedFile::fake()->image('logo.png'), $this->admin)->uuid;

    $this->actingAs($this->admin, 'system_user')
        ->putJson('/api/v1/admin/payout-invoice-template', ($this->payload)(['logo' => $uuid]))
        ->assertOk()
        ->assertJsonPath('data.logo.id', $uuid);

    $this->getJson("/api/v1/admin/payouts/{$this->payout->id}/invoice")->assertOk();

    $this->putJson('/api/v1/admin/payout-invoice-template', ($this->payload)(['logo' => null]))
        ->assertOk()
        ->assertJsonPath('data.logo', null);
    expect(PayoutInvoiceTemplate::current()->getMedia('logo'))->toBeEmpty();
});

test('the logo must be the admin\'s own JPEG or PNG upload', function (): void {
    $other = SystemUserFactory::new()->create();
    $foreign = app(TemporaryMediaService::class)->createTemporary(UploadedFile::fake()->image('logo.png'), $other)->uuid;
    $webp = app(TemporaryMediaService::class)->createTemporary(UploadedFile::fake()->image('logo.webp'), $this->admin)->uuid;

    foreach ([$foreign, $webp] as $uuid) {
        $this->actingAs($this->admin, 'system_user')
            ->putJson('/api/v1/admin/payout-invoice-template', ($this->payload)(['logo' => $uuid]))
            ->assertUnprocessable();
    }

    expect(PayoutInvoiceTemplate::current()->getMedia('logo'))->toBeEmpty();
});

test('the preview renders unsaved changes without saving them', function (?bool $withPayout): void {
    $before = PayoutInvoiceTemplate::current()->toArray();
    $logo = app(TemporaryMediaService::class)->createTemporary(UploadedFile::fake()->image('logo.jpg'), $this->admin)->uuid;

    $response = $this->actingAs($this->admin, 'system_user')->post('/api/v1/admin/payout-invoice-template/preview', [
        ...($this->payload)(['logo' => $logo]),
        'locale' => 'ar',
        'payout_id' => $withPayout ? $this->payout->id : null,
    ]);

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF')
        ->and(PayoutInvoiceTemplate::current()->toArray())->toBe($before)
        ->and(PayoutInvoiceTemplate::current()->getMedia('logo'))->toBeEmpty();
})->with(['real payout' => true, 'sample data' => false]);

test('the preview requires a locale', function (): void {
    $this->actingAs($this->admin, 'system_user')
        ->postJson('/api/v1/admin/payout-invoice-template/preview', ($this->payload)())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['locale']);
});
