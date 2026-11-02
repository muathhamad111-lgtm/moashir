<?php

declare(strict_types=1);

use App\Actions\Files\ScanPendingStoredFile;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Files\MalwareScanStatus;
use App\Enums\Notifications\NotificationCode;
use App\Jobs\Files\ScanStoredFileJob;
use App\Models\Assessments\EvidenceFile;
use App\Models\Identity\User;
use App\Notifications\Entities\EntityNotification;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
});

/**
 * @return list<EntityNotification>
 */
function refusedEvidenceNoticesOf(User $user): array
{
    return array_values(Notification::sent($user, EntityNotification::class)
        ->filter(static fn (EntityNotification $notification): bool => $notification->notice->code === NotificationCode::EvidenceRefused)
        ->all());
}

function pendingEvidenceId(User $owner, mixed $assessment, mixed $version, string $code = 'M1.2'): string
{
    fileScanner()->makeUnavailable();
    $id = uploadEvidence($owner, $assessment, $version, $code, null, evidencePdf('محضر-الاجتماع.pdf', infectedContents()))
        ->assertCreated()
        ->assertJsonPath('data.scan_status', 'pending')
        ->json('data.id');
    fileScanner()->makeAvailable();

    return is_string($id) ? $id : throw new LogicException('The evidence was not stored.');
}

function scanEvidence(string $evidenceId): void
{
    new ScanStoredFileJob(EvidenceFile::query()->where('public_id', $evidenceId)->firstOrFail()->stored_file_id)
        ->withFakeQueueInteractions()
        ->handle(app(ScanPendingStoredFile::class));
}

it('tells the owning entity in-app that an infected evidence file was refused and must be replaced', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = openedAccreditedRequest();
    $owner = ownerOf($assessment->entity);
    $follower = requestFollower();
    $admin = systemAdmin();

    uploadEvidence($owner, $assessment, $version, 'M1.2', null, evidencePdf('محضر-الاجتماع.pdf', infectedContents()))->assertCreated();

    $notices = refusedEvidenceNoticesOf($owner);

    expect($notices)->toHaveCount(1)
        ->and($notices[0]->notice->viaInApp)->toBeTrue()
        ->and($notices[0]->notice->viaMail)->toBeFalse()
        ->and($notices[0]->notice->link)->toBe('/assessment/accredited')
        ->and($notices[0]->notice->body->en)->toContain('M1.2')->toContain($assessment->request_number)->toContain('upload a clean copy')
        ->and($notices[0]->notice->body->ar)->toContain('M1.2')->not->toContain('محضر-الاجتماع')
        ->and($notices[0]->notice->body->en)->not->toContain('محضر-الاجتماع')
        ->and(refusedEvidenceNoticesOf($follower))->toBe([])
        ->and(refusedEvidenceNoticesOf($admin))->toBe([])
        ->and(financeNoticeSent($admin, NotificationCode::RequestProcessingFailed))->toBeTrue();

    switchTo($owner)->getJson("/api/v1/assessments/{$assessment->id}/evidence")->assertOk()->assertJsonPath('data.0.scan_status', 'infected');
});

it('tells the entity that the assessor does not see a file refused after the submission', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = readyAccreditedDraft($version);
    $owner = ownerOf($assessment->entity);
    $evidenceId = pendingEvidenceId($owner, $assessment, $version);

    switchTo($owner)->postJson("/api/v1/assessments/{$assessment->id}/submit")->assertOk()->assertJsonPath('data.status', 'submitted');
    scanEvidence($evidenceId);

    $notices = refusedEvidenceNoticesOf($owner);

    expect(EvidenceFile::query()->where('public_id', $evidenceId)->firstOrFail()->storedFile->malware_scan_status)->toBe(MalwareScanStatus::Infected)
        ->and($notices)->toHaveCount(1)
        ->and($notices[0]->notice->body->en)->toContain('The assessor does not see it');
});

it('sends the refusal notice once even when the scan runs again', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = openedAccreditedRequest();
    $owner = ownerOf($assessment->entity);
    $evidenceId = pendingEvidenceId($owner, $assessment, $version);

    scanEvidence($evidenceId);
    scanEvidence($evidenceId);

    expect(refusedEvidenceNoticesOf($owner))->toHaveCount(1);
});

it('sends no refusal notice for a removed evidence file or a closed request', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = openedAccreditedRequest();
    $owner = ownerOf($assessment->entity);
    $removedId = pendingEvidenceId($owner, $assessment, $version);
    $closedId = pendingEvidenceId($owner, $assessment, $version, 'M1.1');

    switchTo($owner)->deleteJson("/api/v1/assessments/{$assessment->id}/evidence/{$removedId}")->assertNoContent();
    scanEvidence($removedId);
    $assessment->forceFill(['status' => AccreditedAssessmentStatus::Refunded, 'refunded_at' => Date::now()])->saveQuietly();
    scanEvidence($closedId);

    expect(EvidenceFile::query()->where('public_id', $closedId)->firstOrFail()->storedFile->quarantined_at)->not->toBeNull()
        ->and(refusedEvidenceNoticesOf($owner))->toBe([]);
});

it('sends no refusal notice for an infected file of another purpose', function (): void {
    $file = storedTestFile([], infectedContents());

    new ScanStoredFileJob($file->id)->withFakeQueueInteractions()->handle(app(ScanPendingStoredFile::class));

    $refused = 0;
    $sent = Notification::sentNotifications();
    array_walk_recursive($sent, static function (mixed $leaf) use (&$refused): void {
        $refused += $leaf instanceof EntityNotification && $leaf->notice->code === NotificationCode::EvidenceRefused ? 1 : 0;
    });

    expect($file->refresh()->malware_scan_status)->toBe(MalwareScanStatus::Infected)
        ->and($file->quarantined_at)->not->toBeNull()
        ->and($refused)->toBe(0);
});
