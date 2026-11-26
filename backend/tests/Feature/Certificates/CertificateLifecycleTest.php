<?php

declare(strict_types=1);

use App\Enums\Audit\AuditEvent;
use App\Enums\Notifications\NotificationCode;
use App\Models\Audit\Audit;
use App\Models\Certificates\Certificate;
use App\Notifications\Entities\EntityNotification;
use App\Notifications\Identity\SecurityAlertNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

it('lets the System Administrator revoke a certificate with a reason and tells the entity', function (): void {
    ['assessment' => $assessment, 'certificate' => $certificate] = certifiedAccreditedRequest();
    $admin = systemAdmin();
    $owner = ownerOf($assessment->entity);

    switchTo($admin)->postJson("/api/v1/certificates/{$certificate->public_id}/revoke", ['reason' => CERTIFICATE_REASON])->assertOk()
        ->assertJsonPath('data.status', 'revoked')
        ->assertJsonPath('data.revoked_reason', CERTIFICATE_REASON)
        ->assertJsonPath('data.revoked_by.id', $admin->id);

    $audit = Audit::query()->where('event', AuditEvent::CertificateRevoked->value)->sole();
    expect($audit->reason)->toBe(CERTIFICATE_REASON)
        ->and($audit->new_values['status'])->toBe('revoked');

    Notification::assertSentTo($owner, EntityNotification::class, static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::CertificateRevoked
        && str_contains($notification->notice->body->ar, CERTIFICATE_REASON));

    switchTo($owner)->postJson("/api/v1/certificates/{$certificate->public_id}/download")->assertConflict()->assertJsonPath('code', 'certificate.revoked');
    switchTo($admin)->postJson("/api/v1/certificates/{$certificate->public_id}/revoke", ['reason' => CERTIFICATE_REASON])->assertConflict()->assertJsonPath('code', 'certificate.not_issued');
});

it('refuses a revocation without a valid reason or by another role', function (): void {
    ['assessment' => $assessment, 'certificate' => $certificate, 'assessor' => $assessor] = certifiedAccreditedRequest();

    switchTo(systemAdmin())->postJson("/api/v1/certificates/{$certificate->public_id}/revoke")->assertUnprocessable()->assertJsonValidationErrors('reason');
    switchTo(systemAdmin())->postJson("/api/v1/certificates/{$certificate->public_id}/revoke", ['reason' => 'قصير'])->assertUnprocessable()->assertJsonValidationErrors('reason');
    switchTo(ownerOf($assessment->entity))->postJson("/api/v1/certificates/{$certificate->public_id}/revoke", ['reason' => CERTIFICATE_REASON])->assertForbidden();
    switchTo($assessor)->postJson("/api/v1/certificates/{$certificate->public_id}/revoke", ['reason' => CERTIFICATE_REASON])->assertNotFound();

    expect(Certificate::query()->sole()->status->value)->toBe('issued');
});

it('re-issues a certificate with a new number and code and supersedes the previous one', function (): void {
    ['certificate' => $certificate] = certifiedAccreditedRequest();
    $admin = systemAdmin();

    $replacement = switchTo($admin)->postJson("/api/v1/certificates/{$certificate->public_id}/reissue", ['reason' => CERTIFICATE_REASON])->assertCreated()
        ->assertJsonPath('data.status', 'issued')
        ->assertJsonPath('data.replaces.number', $certificate->number)
        ->assertJsonPath('data.reissue_reason', CERTIFICATE_REASON)
        ->json('data');

    $previous = Certificate::query()->whereKey($certificate->id)->sole();
    expect($previous->status->value)->toBe('superseded')
        ->and($previous->superseded_at)->not->toBeNull()
        ->and($replacement['number'])->not->toBe($certificate->number)
        ->and($replacement['verification_code'])->not->toBe($certificate->verification_code)
        ->and(Audit::query()->where('event', AuditEvent::CertificateReissued->value)->count())->toBe(1)
        ->and(Audit::query()->where('event', AuditEvent::CertificateSuperseded->value)->count())->toBe(1);

    switchTo($admin)->postJson("/api/v1/certificates/{$certificate->public_id}/reissue", ['reason' => CERTIFICATE_REASON])->assertConflict()->assertJsonPath('code', 'certificate.superseded');
});

it('re-issues a revoked certificate once', function (): void {
    ['certificate' => $certificate] = certifiedAccreditedRequest();
    $admin = systemAdmin();
    switchTo($admin)->postJson("/api/v1/certificates/{$certificate->public_id}/revoke", ['reason' => CERTIFICATE_REASON])->assertOk();

    switchTo($admin)->postJson("/api/v1/certificates/{$certificate->public_id}/reissue", ['reason' => CERTIFICATE_REASON])->assertCreated();
    switchTo($admin)->postJson("/api/v1/certificates/{$certificate->public_id}/reissue", ['reason' => CERTIFICATE_REASON])->assertConflict()->assertJsonPath('code', 'certificate.replacement_exists');

    expect(Certificate::query()->where('status', 'issued')->count())->toBe(1)
        ->and(Certificate::query()->whereKey($certificate->id)->sole()->status->value)->toBe('revoked');
});

it('raises the Super Admin alert when the Super Admin revokes a certificate', function (): void {
    ['certificate' => $certificate] = certifiedAccreditedRequest();
    $superAdmin = superAdmin();

    switchTo($superAdmin)->postJson("/api/v1/certificates/{$certificate->public_id}/revoke", ['reason' => CERTIFICATE_REASON])->assertOk();

    Notification::assertSentTo($superAdmin, SecurityAlertNotification::class);
    expect(Audit::query()->where('event', AuditEvent::CertificateRevoked->value)->sole()->tags)->toContain('super_admin');
});
