<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Enums\Settings\SettingKey;
use App\Models\Audit\Audit;
use App\Notifications\Identity\SecurityAlertNotification;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

const PATH_SETTINGS = '/api/v1/settings/assessment/paths/';

beforeEach(function (): void {
    Notification::fake();
});

it('serves the settings of each path with defaults', function (): void {
    setIntegrationSetting(SettingKey::AccreditedPrice, '2000.00');

    switchTo(systemAdmin())->getJson(PATH_SETTINGS.'accredited')->assertOk()
        ->assertJsonPath('data.path', 'accredited')
        ->assertJsonPath('data.path_label', ['ar' => 'التقييم المعتمد', 'en' => 'Accredited assessment'])
        ->assertJsonPath('data.review_sla_days', 14)
        ->assertJsonPath('data.review_sla_limits', ['min' => 1, 'max' => 120])
        ->assertJsonPath('data.upload_limits', ['max_file_mb' => 20, 'allowed_types' => ['pdf', 'docx', 'xlsx', 'pptx', 'jpg', 'png'], 'max_files_per_sub' => 10])
        ->assertJsonPath('data.upload_limit_bounds', ['max_file_mb' => 100, 'max_files_per_sub' => 50])
        ->assertJsonPath('data.is_paid', true)
        ->assertJsonPath('data.price', ['price_before_vat' => '2000.00', 'vat_rate' => '15.00', 'vat_amount' => '300.00', 'total' => '2300.00', 'currency' => 'SAR'])
        ->assertJsonPath('data.entity_texts', ['intro' => null, 'instructions' => null, 'after_submission' => null]);

    test()->getJson(PATH_SETTINGS.'advanced')->assertOk()
        ->assertJsonPath('data.path', 'advanced')
        ->assertJsonPath('data.review_sla_days', 14)
        ->assertJsonPath('data.is_paid', false)
        ->assertJsonPath('data.price', null);
    test()->getJson(PATH_SETTINGS.'self')->assertNotFound();
});

it('updates each path independently and audits every changed key', function (): void {
    $admin = systemAdmin();

    switchTo($admin)->putJson(PATH_SETTINGS.'advanced', [
        'review_sla_days' => 21,
        'upload_max_file_mb' => 5,
        'evidence_allowed_types' => ['pdf'],
        'evidence_max_files_per_sub' => 3,
        'entity_texts' => ['intro' => ['ar' => 'مسار الجائزة للجهات المؤهلة', 'en' => 'The Award path for eligible entities']],
    ])->assertOk()
        ->assertJsonPath('data.review_sla_days', 21)
        ->assertJsonPath('data.upload_limits', ['max_file_mb' => 5, 'allowed_types' => ['pdf'], 'max_files_per_sub' => 3])
        ->assertJsonPath('data.entity_texts.intro', ['ar' => 'مسار الجائزة للجهات المؤهلة', 'en' => 'The Award path for eligible entities'])
        ->assertJsonPath('data.entity_texts.instructions', null);

    test()->getJson(PATH_SETTINGS.'accredited')->assertOk()
        ->assertJsonPath('data.review_sla_days', 14)
        ->assertJsonPath('data.upload_limits.max_file_mb', 20)
        ->assertJsonPath('data.entity_texts.intro', null);
    test()->getJson('/api/v1/settings/assessment')->assertOk()->assertJsonPath('data.advanced_initial_review_sla_days', 21);

    test()->putJson(PATH_SETTINGS.'advanced', ['entity_texts' => ['intro' => null, 'after_submission' => ['ar' => 'سنتواصل معكم', 'en' => 'We will contact you']]])->assertOk()
        ->assertJsonPath('data.entity_texts', ['intro' => null, 'instructions' => null, 'after_submission' => ['ar' => 'سنتواصل معكم', 'en' => 'We will contact you']]);

    expect(Audit::query()->where('event', AuditEvent::AssessmentSettingsUpdated->value)->count())->toBe(6);
});

it('updates the accredited price through the path settings with the finance audit', function (): void {
    switchTo(systemAdmin())->putJson(PATH_SETTINGS.'accredited', ['price' => '2500.00'])->assertOk()
        ->assertJsonPath('data.price.price_before_vat', '2500.00')
        ->assertJsonPath('data.price.total', '2875.00');

    test()->getJson('/api/v1/settings/finance')->assertOk()->assertJsonPath('data.accredited_price', '2500.00');
    test()->getJson('/api/v1/content/prices')->assertOk();

    expect(Audit::query()->where('event', AuditEvent::FinanceSettingsUpdated->value)->count())->toBe(1);

    test()->putJson(PATH_SETTINGS.'advanced', ['price' => '100.00'])->assertUnprocessable()->assertJsonValidationErrors(['price']);
});

it('moves the open accredited due dates when the accredited review SLA changes', function (): void {
    Date::setTestNow('2026-11-01 08:00:00');
    $version = publishedAccreditedCriteria();
    $open = reviewedAccreditedRequest($version, assessorUser());

    switchTo(systemAdmin())->putJson(PATH_SETTINGS.'accredited', ['review_sla_days' => 7])->assertOk()->assertJsonPath('data.review_sla_days', 7);

    expect($open->refresh()->due_at?->toIso8601String())->toBe('2026-11-08T08:00:00+00:00');
});

it('requires a reason from the Super Admin', function (): void {
    $admin = superAdmin();

    switchTo($admin)->putJson(PATH_SETTINGS.'accredited', ['evidence_max_files_per_sub' => 4])->assertUnprocessable()->assertJsonPath('code', 'reason_required');
    test()->putJson(PATH_SETTINGS.'accredited', ['evidence_max_files_per_sub' => 4, 'reason' => 'تقليل عدد الملفات لكل معيار فرعي'])->assertOk()
        ->assertJsonPath('data.upload_limits.max_files_per_sub', 4);

    Notification::assertSentTo($admin, SecurityAlertNotification::class);
});

it('refuses the path settings to users without the assessment settings permission', function (RoleKey $role): void {
    switchTo(staffUser($role))->getJson(PATH_SETTINGS.'accredited')->assertForbidden();
    test()->putJson(PATH_SETTINGS.'accredited', ['review_sla_days' => 5])->assertForbidden();
})->with([
    'requests follower' => [RoleKey::RequestFollower],
    'assessor' => [RoleKey::Assessor],
    'finance officer' => [RoleKey::FinanceOfficer],
]);

it('refuses the path settings to entities and guests', function (): void {
    test()->getJson(PATH_SETTINGS.'accredited')->assertUnauthorized();
    switchTo(ownerOf(entityWithOwner()))->putJson(PATH_SETTINGS.'accredited', ['review_sla_days' => 5])->assertForbidden();
});

it('refuses invalid path settings', function (array $payload, string $field): void {
    switchTo(systemAdmin())->putJson(PATH_SETTINGS.'accredited', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    'sla too long' => [['review_sla_days' => 121], 'review_sla_days'],
    'file too large' => [['upload_max_file_mb' => 101], 'upload_max_file_mb'],
    'no types' => [['evidence_allowed_types' => []], 'evidence_allowed_types'],
    'unknown type' => [['evidence_allowed_types' => ['exe']], 'evidence_allowed_types.0'],
    'too many files' => [['evidence_max_files_per_sub' => 51], 'evidence_max_files_per_sub'],
    'price format' => [['price' => '10'], 'price'],
    'text without english' => [['entity_texts' => ['intro' => ['ar' => 'نص عربي']]], 'entity_texts.intro.en'],
    'unknown text' => [['entity_texts' => ['banner' => ['ar' => 'نص', 'en' => 'Text']]], 'entity_texts'],
    'text too long' => [['entity_texts' => ['intro' => ['ar' => str_repeat('ن', 2001), 'en' => 'Text']]], 'entity_texts.intro.ar'],
]);

it('keeps the legacy assessment settings as aliases of the accredited path', function (): void {
    switchTo(systemAdmin())->putJson('/api/v1/settings/assessment', [
        'upload_max_file_mb' => 8,
        'evidence_allowed_types' => ['pdf', 'png'],
        'evidence_max_files_per_sub' => 2,
        'general_upload_max_file_mb' => 12,
    ])->assertOk()
        ->assertJsonPath('data.upload_max_file_mb', 8)
        ->assertJsonPath('data.general_upload_max_file_mb', 12);

    test()->getJson(PATH_SETTINGS.'accredited')->assertOk()
        ->assertJsonPath('data.upload_limits', ['max_file_mb' => 8, 'allowed_types' => ['pdf', 'png'], 'max_files_per_sub' => 2]);
    test()->getJson(PATH_SETTINGS.'advanced')->assertOk()
        ->assertJsonPath('data.upload_limits.max_file_mb', 20);
});

it('applies the upload limits of the path of the request to evidence uploads', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = openedAccreditedRequest();
    $owner = ownerOf($assessment->entity);
    setIntegrationSetting(SettingKey::AdvancedEvidenceAllowedTypes, ['png']);

    uploadEvidence($owner, $assessment, $version)->assertCreated();

    setIntegrationSetting(SettingKey::AccreditedEvidenceAllowedTypes, ['png']);

    uploadEvidence($owner, $assessment, $version)->assertUnprocessable()->assertJsonPath('code', 'file.content_mismatch');

    switchTo($owner)->getJson("/api/v1/assessments/{$assessment->id}/form")->assertOk()
        ->assertJsonPath('data.upload_limits.allowed_types', ['png']);
});

it('publishes the entity-facing guide of each path without authentication', function (): void {
    setIntegrationSetting(SettingKey::AccreditedEntityTexts, ['intro' => ['ar' => 'تقييم يجريه مقيّم معتمد', 'en' => 'An assessment by an accredited assessor'], 'instructions' => null, 'after_submission' => null]);

    test()->getJson('/api/v1/content/assessment-paths/accredited')->assertOk()
        ->assertJsonPath('data.path', 'accredited')
        ->assertJsonPath('data.path_label.en', 'Accredited assessment')
        ->assertJsonPath('data.entity_texts.intro.en', 'An assessment by an accredited assessor')
        ->assertJsonPath('data.review_sla_days', 14)
        ->assertJsonPath('data.upload_limits.max_files_per_sub', 10)
        ->assertJsonMissingPath('data.upload_limit_bounds');
    test()->getJson('/api/v1/content/assessment-paths/advanced')->assertOk()->assertJsonPath('data.price', null);
    test()->getJson('/api/v1/content/assessment-paths/self')->assertNotFound();
});
