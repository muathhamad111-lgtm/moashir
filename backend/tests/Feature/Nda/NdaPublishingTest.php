<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Enums\Identity\TermsKind;
use App\Enums\Notifications\NotificationCode;
use App\Models\Audit\Audit;
use App\Models\Identity\TermsVersion;
use App\Notifications\Entities\EntityNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

it('drafts, previews the reach of and publishes a version, then sends NDA-01 to in-scope staff only', function (): void {
    $admin = systemAdmin();
    $assessor = staffUser(RoleKey::Assessor);
    $finance = staffUser(RoleKey::FinanceOfficer);
    $entity = entityUser();

    signIn($admin)->postJson('/api/v1/settings/nda/draft', legalPayload(['version' => '1.0']))->assertCreated()->assertJsonPath('data.kind', 'nda');
    signIn($admin)->getJson('/api/v1/settings/nda/draft/impact')
        ->assertOk()
        ->assertJsonPath('data.total', 2)
        ->assertJsonFragment(['role' => 'assessor', 'count' => 1]);
    signIn($admin)->postJson('/api/v1/settings/nda/draft/publish', ndaPublishPayload())
        ->assertOk()
        ->assertJsonPath('data.status', 'current')
        ->assertJsonPath('data.change_summary.en', 'A clause that forbids screenshots of screens and evidence was added.');

    $published = TermsVersion::query()->current()->where('kind', TermsKind::Nda->value)->sole();
    $audit = Audit::query()->where('event', AuditEvent::NdaPublished->value)->sole();
    expect($audit->new_values['change_summary']['ar'] ?? null)->toBe('إضافة بند يمنع تصوير الشاشة والشواهد.')
        ->and($audit->new_values['body_sha256']['ar'] ?? null)->toBe(hash('sha256', $published->body->ar))
        ->and((string) $audit->reason)->toContain('مراجعة الشؤون القانونية');

    $isNda01 = static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::NdaVersionPublished;
    Notification::assertSentTo($assessor, EntityNotification::class, $isNda01);
    Notification::assertSentTo($admin, EntityNotification::class, $isNda01);
    Notification::assertNotSentTo($finance, EntityNotification::class);
    Notification::assertNotSentTo($entity, EntityNotification::class);
});

it('requires a change summary in both languages, a reason and the confirmation', function (array $overrides, string $field): void {
    ndaDraft();

    signIn(systemAdmin())->postJson('/api/v1/settings/nda/draft/publish', ndaPublishPayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'no summary' => [['change_summary' => null], 'change_summary'],
    'no English summary' => [['change_summary' => ['ar' => 'ملخص']], 'change_summary.en'],
    'no reason' => [['reason' => ''], 'reason'],
    'short reason' => [['reason' => 'قصير'], 'reason'],
    'not confirmed' => [['confirm_irreversible' => false], 'confirm_irreversible'],
]);

it('refuses a draft without the English text', function (): void {
    signIn(systemAdmin())->postJson('/api/v1/settings/nda/draft', legalPayload(['body' => ['ar' => 'نص الاتفاقية']]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('body.en');
});

it('answers 404 when there is no draft to publish', function (): void {
    signIn(systemAdmin())->postJson('/api/v1/settings/nda/draft/publish', ndaPublishPayload())
        ->assertNotFound()
        ->assertJsonPath('code', 'nda.no_draft');
});

it('keeps one draft at a time', function (): void {
    ndaDraft();

    signIn(systemAdmin())->postJson('/api/v1/settings/nda/draft', legalPayload(['version' => '2.0']))->assertConflict();
});

it('refuses the agreement settings to staff without nda.manage, content officers included', function (string $method, string $uri): void {
    ndaDraft();

    signIn(contentOfficer())->json($method, $uri, ndaPublishPayload())->assertForbidden();
})->with([
    ['GET', '/api/v1/settings/nda'],
    ['GET', '/api/v1/settings/nda/versions'],
    ['POST', '/api/v1/settings/nda/draft/publish'],
    ['GET', '/api/v1/settings/nda/acceptances'],
    ['POST', '/api/v1/settings/nda/reminders'],
]);

it('keeps the agreement out of the public legal pages and their admin', function (): void {
    $draft = ndaDraft();
    publishedNda();

    $officer = contentOfficer();

    $this->getJson('/api/v1/content/legal/nda')->assertNotFound();
    signIn($officer)->getJson('/api/v1/admin/content/legal/nda/versions')->assertNotFound();
    signIn($officer)->getJson("/api/v1/admin/content/legal/versions/{$draft->id}")->assertForbidden();
    signIn($officer)->postJson("/api/v1/admin/content/legal/versions/{$draft->id}/publish")->assertForbidden();
});

it('lists the versions and shows one with its text', function (): void {
    $version = publishedNda();
    ndaDraft();
    $admin = systemAdmin();
    acceptNdaAs($admin, $version);

    signIn($admin)->getJson('/api/v1/settings/nda/versions')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.status', 'draft');
    signIn($admin)->getJson("/api/v1/settings/nda/versions/{$version->id}")->assertOk()->assertJsonPath('data.body.en', $version->body->en);
    signIn($admin)->getJson('/api/v1/settings/nda/versions/'.legalDraft()->id)->assertNotFound();
});
