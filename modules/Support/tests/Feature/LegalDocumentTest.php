<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Support\Database\Factories\SystemUserFactory;
use Modules\Support\Database\Seeders\SystemRolePermissionSeeder;
use Modules\Support\Enums\LegalAudience;
use Modules\Support\Enums\LegalDocumentType;
use Modules\Support\Enums\SystemPermission;
use Modules\Support\Models\LegalDocument;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SystemRolePermissionSeeder::class);
    $this->admin = SystemUserFactory::new()->superAdmin()->create();
    $this->base = '/api/v1/admin/legal-documents';
    $this->payload = ['content' => ['en' => '<h2>Privacy</h2><p>We care.</p>', 'ar' => '<h2>الخصوصية</h2><p>نهتم.</p>']];
});

test('admin endpoints need the legal documents permission', function (): void {
    $this->getJson($this->base)->assertUnauthorized();

    $this->actingAs(SystemUserFactory::new()->create(), 'system_user');
    $this->getJson($this->base)->assertForbidden();
    $this->putJson("{$this->base}/client/terms", $this->payload)->assertForbidden();

    $inactive = SystemUserFactory::new()->inactive()->withPermissions([SystemPermission::ManageLegalDocuments])->create();
    $this->actingAs($inactive, 'system_user')->getJson($this->base)->assertForbidden();
});

test('admin lists the four documents and updates one', function (): void {
    $this->actingAs($this->admin, 'system_user');

    $this->getJson($this->base)
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('data.0.content.en', '');

    $this->putJson("{$this->base}/center/privacy_policy", $this->payload)
        ->assertOk()
        ->assertJsonPath('data.audience', 'center')
        ->assertJsonPath('data.content.ar', '<h2>الخصوصية</h2><p>نهتم.</p>')
        ->assertJsonPath('data.url.ar', route('legal.show', ['audience' => 'center', 'type' => 'privacy_policy', 'lang' => 'ar']));

    $this->getJson("{$this->base}/center/privacy_policy")->assertOk()->assertJsonPath('data.content.en', '<h2>Privacy</h2><p>We care.</p>');
    $this->getJson("{$this->base}/client/privacy_policy")->assertOk()->assertJsonPath('data.content.en', '');
    $this->getJson("{$this->base}/vendor/terms")->assertNotFound();
});

test('update requires both locales', function (): void {
    $this->actingAs($this->admin, 'system_user')
        ->putJson("{$this->base}/client/terms", ['content' => ['en' => '<p>Only English</p>']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['content.ar']);
});

test('saved html is sanitized but keeps formatting', function (): void {
    $html = '<h2 onclick="steal()">Terms</h2><script>alert(1)</script><p style="text-align: center; position: fixed">Hi</p>'
        .'<p style="color: red">Plain</p><a href="javascript:alert(1)">bad</a><a href="https://khyyal.com">good</a>';

    $this->actingAs($this->admin, 'system_user')
        ->putJson("{$this->base}/client/terms", ['content' => ['en' => $html, 'ar' => $html]])
        ->assertOk();

    $saved = LegalDocument::findFor(LegalAudience::Client, LegalDocumentType::Terms)->getTranslation('content', 'en');

    expect($saved)
        ->toContain('<h2>Terms</h2>', '<p style="text-align: center">Hi</p>', '<p>Plain</p>', 'href="https://khyyal.com"')
        ->not->toContain('script', 'onclick', 'javascript', 'position', 'color');
});

test('the public page renders the requested locale', function (): void {
    LegalDocument::findFor(LegalAudience::Client, LegalDocumentType::PrivacyPolicy)
        ->update(['content' => ['en' => '<p>English body</p>', 'ar' => '<p>نص عربي</p>']]);

    $this->get('/legal/client/privacy_policy?lang=ar')
        ->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee('سياسة الخصوصية')
        ->assertSee('<p>نص عربي</p>', false);

    $this->get('/legal/client/privacy_policy?lang=xx')->assertOk()->assertSee('<p>English body</p>', false);
    $this->get('/legal/center/terms')->assertOk()->assertSee('This page is not available yet.');
    $this->get('/legal/client/cookies')->assertNotFound();
});

test('apps get their own legal links in the request locale', function (string $audience): void {
    $this->getJson("/api/v1/{$audience}/legal", ['Accept-Language' => 'ar'])
        ->assertOk()
        ->assertExactJson(['data' => [
            'privacy_policy_url' => route('legal.show', ['audience' => $audience, 'type' => 'privacy_policy', 'lang' => 'ar']),
            'terms_url' => route('legal.show', ['audience' => $audience, 'type' => 'terms', 'lang' => 'ar']),
        ]]);

    $this->getJson("/api/v1/{$audience}/legal", ['Accept-Language' => 'fr'])
        ->assertOk()
        ->assertJsonPath('data.terms_url', route('legal.show', ['audience' => $audience, 'type' => 'terms', 'lang' => 'en']));
})->with(['client', 'center']);
