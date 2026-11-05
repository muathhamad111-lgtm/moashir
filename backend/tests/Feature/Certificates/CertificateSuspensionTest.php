<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Notifications\NotificationCode;
use App\Models\Audit\Audit;
use App\Models\Certificates\Certificate;
use App\Notifications\Entities\EntityNotification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

it('suspends the certificate when the Super Admin reopens the assessment and tells the entity', function (): void {
    ['assessment' => $assessment, 'certificate' => $certificate] = certifiedAccreditedRequest();
    $owner = ownerOf($assessment->entity);
    $admin = systemAdmin();

    switchTo(superAdmin())->postJson("/api/v1/assessments/{$assessment->id}/reopen", ['reason' => SUPER_ADMIN_REASON])->assertOk();

    $suspended = Certificate::query()->whereKey($certificate->id)->sole();
    expect($suspended->status->value)->toBe('suspended')
        ->and($suspended->suspended_at)->not->toBeNull()
        ->and($suspended->suspended_reason)->toBe(SUPER_ADMIN_REASON);

    $audit = Audit::query()->where('event', AuditEvent::CertificateSuspended->value)->sole();
    expect($audit->old_values)->toBe(['status' => 'issued'])
        ->and($audit->new_values['status'])->toBe('suspended');

    Notification::assertSentTo($owner, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::CertificateSuspended
        && $notification->notice->link === '/certificates'
        && ! str_contains($notification->notice->body->ar, SUPER_ADMIN_REASON));
    Notification::assertSentTo($admin, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::CertificateSuspended);

    asGuest()->getJson('/api/v1/verify/'.$certificate->verification_code)->assertOk()
        ->assertJsonPath('data.result', 'under_review')
        ->assertJsonPath('data.certificate.number', $certificate->number)
        ->assertJsonPath('data.certificate.level', null)
        ->assertJsonPath('data.certificate.percent', null);

    switchTo($owner)->postJson("/api/v1/certificates/{$certificate->public_id}/download")->assertConflict()->assertJsonPath('code', 'certificate.suspended');
    switchTo($owner)->getJson("/api/v1/certificates/{$certificate->public_id}")->assertOk()
        ->assertJsonPath('data.status', 'suspended')
        ->assertJsonMissingPath('data.suspended_reason');
    switchTo($admin)->getJson('/api/v1/certificates?filter[status]=suspended')->assertOk()->assertJsonCount(1, 'data');
});

it('replaces the suspended certificate with a new one when the assessment is completed again', function (): void {
    ['assessment' => $assessment, 'certificate' => $certificate, 'assessor' => $assessor] = certifiedAccreditedRequest();
    $owner = ownerOf($assessment->entity);

    switchTo(superAdmin())->postJson("/api/v1/assessments/{$assessment->id}/reopen", ['reason' => SUPER_ADMIN_REASON])->assertOk();
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/complete")->assertOk()->assertJsonPath('data.status', 'completed');

    $previous = Certificate::query()->whereKey($certificate->id)->sole();
    $replacement = Certificate::query()->where('replaces_certificate_id', $certificate->id)->sole();

    expect($previous->status->value)->toBe('superseded')
        ->and($previous->superseded_at)->not->toBeNull()
        ->and($replacement->status->value)->toBe('issued')
        ->and($replacement->number)->not->toBe($certificate->number)
        ->and($replacement->reissue_reason)->toBe(SUPER_ADMIN_REASON)
        ->and(Audit::query()->where('event', AuditEvent::CertificateSuperseded->value)->sole()->old_values)->toBe(['status' => 'suspended']);

    Notification::assertSentTo($owner, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::AccreditedCertificateIssued
        && str_contains($notification->notice->body->ar, $replacement->number));

    asGuest()->getJson('/api/v1/verify/'.$certificate->verification_code)->assertOk()->assertJsonPath('data.result', 'superseded');
    asGuest()->getJson('/api/v1/verify/'.$replacement->verification_code)->assertOk()->assertJsonPath('data.result', 'valid');
});

it('suspends the certificate when a completed assessment is forced back into the workflow', function (): void {
    ['assessment' => $assessment, 'certificate' => $certificate] = certifiedAccreditedRequest();

    switchTo(superAdmin())->postJson("/api/v1/assessments/{$assessment->id}/transition/force", ['to_status' => 'submitted', 'reason' => SUPER_ADMIN_REASON])->assertOk();

    expect(Certificate::query()->whereKey($certificate->id)->sole()->status->value)->toBe('suspended');
});

it('keeps the certificate suspended until it is issued again when the assessor completes without one', function (): void {
    ['assessment' => $assessment, 'certificate' => $certificate, 'assessor' => $assessor] = certifiedAccreditedRequest();

    switchTo(superAdmin())->postJson("/api/v1/assessments/{$assessment->id}/reopen", ['reason' => SUPER_ADMIN_REASON])->assertOk();
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/complete", ['issue_certificate' => false])->assertOk();

    expect(Certificate::query()->whereKey($certificate->id)->sole()->status->value)->toBe('suspended');

    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/certificate")->assertCreated()
        ->assertJsonPath('data.replaces.number', $certificate->number);

    expect(Certificate::query()->whereKey($certificate->id)->sole()->status->value)->toBe('superseded')
        ->and(Certificate::query()->where('status', 'issued')->count())->toBe(1);
});

it('lets the System Administrator revoke a suspended certificate', function (): void {
    ['assessment' => $assessment, 'certificate' => $certificate] = certifiedAccreditedRequest();
    switchTo(superAdmin())->postJson("/api/v1/assessments/{$assessment->id}/reopen", ['reason' => SUPER_ADMIN_REASON])->assertOk();

    switchTo(systemAdmin())->postJson("/api/v1/certificates/{$certificate->public_id}/revoke", ['reason' => CERTIFICATE_REASON])->assertOk()
        ->assertJsonPath('data.status', 'revoked');

    expect(Audit::query()->where('event', AuditEvent::CertificateRevoked->value)->sole()->old_values)->toBe(['status' => 'suspended']);
    asGuest()->getJson('/api/v1/verify/'.$certificate->verification_code)->assertOk()->assertJsonPath('data.result', 'revoked');
});

it('leaves a request without certificate untouched on reopen', function (): void {
    setAccreditedCertificateIssuance(false);
    $assessment = completedAccreditedRequest(publishedAccreditedCriteria(), assessorUser());

    switchTo(superAdmin())->postJson("/api/v1/assessments/{$assessment->id}/reopen", ['reason' => SUPER_ADMIN_REASON])->assertOk();

    expect(Certificate::query()->count())->toBe(0)
        ->and(Audit::query()->where('event', AuditEvent::CertificateSuspended->value)->exists())->toBeFalse();
});

it('never lets the database un-suspend or rewrite a suspension', function (): void {
    ['assessment' => $assessment, 'certificate' => $certificate] = certifiedAccreditedRequest();
    switchTo(superAdmin())->postJson("/api/v1/assessments/{$assessment->id}/reopen", ['reason' => SUPER_ADMIN_REASON])->assertOk();

    $update = static fn (array $values): Closure => static fn (): int => DB::transaction(static fn (): int => DB::table('certificates')->where('id', $certificate->id)->update($values));

    expect($update(['status' => 'issued']))->toThrow(QueryException::class, 'append_only')
        ->and($update(['suspended_reason' => 'سبب آخر']))->toThrow(QueryException::class, 'append_only')
        ->and(Certificate::query()->whereKey($certificate->id)->sole()->status->value)->toBe('suspended');
});
