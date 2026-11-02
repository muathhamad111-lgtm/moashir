<?php

declare(strict_types=1);

use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Audit\AuditEvent;
use App\Enums\Files\MalwareScanStatus;
use App\Enums\Notifications\NotificationCode;
use App\Enums\Settings\SettingKey;
use App\Jobs\Assessments\BuildEvidenceBundleJob;
use App\Models\Assessments\EvidenceBundle;
use App\Models\Assessments\EvidenceFile;
use App\Models\Audit\Audit;
use App\Models\Files\StoredFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
});

function minimalDocx(): string
{
    $path = (string) tempnam(sys_get_temp_dir(), 'moashir-docx-');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships/>');
    $zip->addFromString('word/document.xml', '<?xml version="1.0"?><document/>');
    $zip->close();
    $contents = (string) file_get_contents($path);
    unlink($path);

    return $contents;
}

it('accepts evidence on the own draft, scans it in the background and lists it clean', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = openedAccreditedRequest();
    $owner = ownerOf($assessment->entity);
    $policyId = subOf($version, 'M1.1')->id;

    $response = uploadEvidence($owner, $assessment, $version)->assertCreated()
        ->assertJsonPath('data.sub_criterion_id', $policyId)
        ->assertJsonPath('data.code', 'M1.1')
        ->assertJsonPath('data.attachment_key', POLICY_DOCUMENT_KEY)
        ->assertJsonPath('data.file_name', 'سياسة-الحوكمة.pdf')
        ->assertJsonPath('data.mime_type', 'application/pdf');

    $evidence = EvidenceFile::query()->where('public_id', $response->json('data.id'))->firstOrFail();

    expect($evidence->storedFile->malware_scan_status)->toBe(MalwareScanStatus::Clean)
        ->and($evidence->storedFile->attachable_id)->toBe($evidence->id)
        ->and(Audit::query()->where('event', AuditEvent::EvidenceUploaded->value)->where('auditable_id', $assessment->id)->exists())->toBeTrue();

    switchTo($owner)->getJson("/api/v1/assessments/{$assessment->id}/evidence")->assertOk()
        ->assertJsonPath('data.0.id', $evidence->public_id)
        ->assertJsonPath('data.0.scan_status', 'clean');
    switchTo($owner)->getJson("/api/v1/assessments/{$assessment->id}/evidence?filter[sub_criterion_id]=".subOf($version, 'M1.2')->id)->assertOk()->assertJsonCount(0, 'data');
});

it('accepts an office document detected by its content', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = openedAccreditedRequest();

    uploadEvidence(ownerOf($assessment->entity), $assessment, $version, 'M1.2', null, UploadedFile::fake()->createWithContent('الخطة-التشغيلية.docx', minimalDocx()))
        ->assertCreated()
        ->assertJsonPath('data.mime_type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
});

it('refuses files whose type is not allowed or whose content does not match', function (string $name, string $contents): void {
    $version = publishedAccreditedCriteria();
    $assessment = openedAccreditedRequest();

    uploadEvidence(ownerOf($assessment->entity), $assessment, $version, 'M1.1', null, UploadedFile::fake()->createWithContent($name, $contents))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'file.content_mismatch');

    expect(StoredFile::query()->count())->toBe(0);
})->with([
    'plain text' => ['ملاحظات.txt', 'نص عادي لا يقبل كشاهد'],
    'renamed text as pdf' => ['تقرير.pdf', 'ليس ملف PDF حقيقي'],
]);

it('follows the allowed types and the per-sub limit from the settings', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = openedAccreditedRequest();
    $owner = ownerOf($assessment->entity);
    setIntegrationSetting(SettingKey::EvidenceAllowedTypes, ['png']);

    uploadEvidence($owner, $assessment, $version)->assertUnprocessable()->assertJsonPath('code', 'file.content_mismatch');

    setIntegrationSetting(SettingKey::EvidenceAllowedTypes, ['pdf']);
    setIntegrationSetting(SettingKey::EvidenceMaxFilesPerSub, 1);
    uploadEvidence($owner, $assessment, $version)->assertCreated();
    uploadEvidence($owner, $assessment, $version, 'M1.1', 'board_minutes')->assertUnprocessable()
        ->assertJsonPath('code', 'attachment.sub_full')
        ->assertJsonPath('meta.max_files_per_sub', 1);
});

it('validates the evidence target', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = openedAccreditedRequest();
    $owner = ownerOf($assessment->entity);

    uploadEvidence($owner, $assessment, $version, 'M1.2', POLICY_DOCUMENT_KEY)->assertUnprocessable()->assertJsonPath('code', 'attachment.key_invalid');
    switchTo($owner)->post("/api/v1/assessments/{$assessment->id}/evidence", ['file' => evidencePdf(), 'sub_criterion_id' => 999_999], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonPath('code', 'assessment.sub_not_in_version');
    switchTo($owner)->post("/api/v1/assessments/{$assessment->id}/evidence", ['sub_criterion_id' => subOf($version, 'M1.1')->id], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors(['file']);
});

it('keeps pending and infected evidence away from the assessor while the entity sees the scan status', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = reviewedAccreditedRequest($version, $assessor);
    $owner = ownerOf($assessment->entity);
    $assessment->forceFill(['status' => AccreditedAssessmentStatus::Draft, 'submitted_at' => null, 'due_at' => null, 'review_sla_days' => null])->saveQuietly();

    fileScanner()->makeUnavailable();
    $pending = uploadEvidence($owner, $assessment, $version, 'M1.2', null)->assertCreated()->assertJsonPath('data.scan_status', 'pending');
    fileScanner()->makeAvailable();
    $infected = uploadEvidence($owner, $assessment, $version, 'M1.2', null, evidencePdf('مرفق.pdf', infectedContents()))->assertCreated();

    expect(EvidenceFile::query()->where('public_id', $infected->json('data.id'))->firstOrFail()->storedFile->malware_scan_status)->toBe(MalwareScanStatus::Infected);

    switchTo($owner)->getJson("/api/v1/assessments/{$assessment->id}/evidence")->assertOk()->assertJsonCount(3, 'data');
    $listed = switchTo($assessor)->getJson("/api/v1/assessments/{$assessment->id}/evidence")->assertOk()->assertJsonCount(1, 'data');

    expect($listed->json('data.0.scan_status'))->toBe('clean');

    switchTo($assessor)->getJson("/api/v1/assessments/{$assessment->id}/form")->assertOk()->assertJsonCount(1, 'data.evidence');

    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/evidence/{$pending->json('data.id')}/download")->assertConflict()->assertJsonPath('code', 'file.not_clean');
});

it('removes evidence from the own draft and deletes its binary', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = openedAccreditedRequest();
    $owner = ownerOf($assessment->entity);
    $id = uploadEvidence($owner, $assessment, $version)->assertCreated()->json('data.id');
    $evidence = EvidenceFile::query()->where('public_id', $id)->firstOrFail();
    $path = $evidence->storedFile->path;

    switchTo($owner)->deleteJson("/api/v1/assessments/{$assessment->id}/evidence/{$id}")->assertNoContent();

    expect($evidence->refresh()->status->value)->toBe('removed')
        ->and(Storage::disk('local')->exists($path))->toBeFalse()
        ->and(StoredFile::withTrashed()->findOrFail($evidence->stored_file_id)->trashed())->toBeTrue();

    switchTo($owner)->getJson("/api/v1/assessments/{$assessment->id}/evidence")->assertOk()->assertJsonCount(0, 'data');
    switchTo($owner)->deleteJson("/api/v1/assessments/{$assessment->id}/evidence/{$id}")->assertNotFound();
});

it('refuses evidence changes after submission and from other accounts', function (): void {
    $version = publishedAccreditedCriteria();
    $assessment = submittedAccreditedRequest($version);
    $owner = ownerOf($assessment->entity);
    $id = EvidenceFile::query()->where('assessment_id', $assessment->id)->value('public_id');

    uploadEvidence($owner, $assessment, $version)->assertConflict()->assertJsonPath('code', 'state.transition_illegal');
    switchTo($owner)->deleteJson("/api/v1/assessments/{$assessment->id}/evidence/{$id}")->assertConflict();
    uploadEvidence(ownerOf(entityWithOwner()), $assessment, $version)->assertNotFound();
    uploadEvidence(requestFollower(), $assessment, $version)->assertForbidden();
});

it('issues signed evidence downloads to the owner and the assigned assessor only', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = assignedAccreditedRequest($version, $assessor);
    $id = EvidenceFile::query()->where('assessment_id', $assessment->id)->value('public_id');
    $endpoint = "/api/v1/assessments/{$assessment->id}/evidence/{$id}/download";

    $url = (string) switchTo($assessor)->postJson($endpoint)->assertOk()->assertJsonPath('data.file_name', 'سياسة-الحوكمة.pdf')->json('data.url');

    expect(test()->get($url)->assertOk()->streamedContent())->toStartWith('%PDF-')
        ->and(Audit::query()->where('event', AuditEvent::FileDownloaded->value)->count())->toBe(1);

    switchTo(ownerOf($assessment->entity))->postJson($endpoint)->assertOk();
    switchTo(requestFollower())->postJson($endpoint)->assertOk();
    switchTo(assessorUser())->postJson($endpoint)->assertNotFound();
    switchTo(assessorUser())->get($url)->assertNotFound();
    switchTo(requestFollower())->get($url)->assertForbidden()->assertJsonPath('code', 'signature.invalid');
    switchTo(ownerOf(entityWithOwner()))->postJson($endpoint)->assertNotFound();
});

it('builds the evidence bundle at once for a small request and lets the requester download it', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = assignedAccreditedRequest($version, $assessor);

    $bundle = switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/evidence/bundles")->assertCreated()
        ->assertJsonPath('data.status', 'ready')
        ->assertJsonPath('data.file_count', 1)
        ->assertJsonPath('data.is_downloadable', true);
    $bundleId = $bundle->json('data.id');

    $url = (string) switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/evidence/bundles/{$bundleId}/download")->assertOk()->json('data.url');
    $zip = test()->get($url)->assertOk()->assertHeader('Content-Type', 'application/zip')->streamedContent();

    expect(str_starts_with($zip, "PK\x03\x04"))->toBeTrue()
        ->and(Audit::query()->where('event', AuditEvent::EvidenceBundleRequested->value)->count())->toBe(1);

    switchTo(requestFollower())->getJson("/api/v1/assessments/{$assessment->id}/evidence/bundles/{$bundleId}")->assertNotFound();
    switchTo(ownerOf($assessment->entity))->postJson("/api/v1/assessments/{$assessment->id}/evidence/bundles")->assertForbidden();
});

it('prepares a large evidence bundle in the background and notifies the requester', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = assignedAccreditedRequest($version, $assessor);
    Config::set('moashir.assessment.evidence_bundle_sync_max_bytes', 10);
    Queue::fake([BuildEvidenceBundleJob::class]);

    $id = switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/evidence/bundles")->assertAccepted()
        ->assertJsonPath('data.status', 'preparing')
        ->json('data.id');
    $bundle = EvidenceBundle::query()->where('public_id', $id)->firstOrFail();

    Queue::assertPushed(BuildEvidenceBundleJob::class, static fn (BuildEvidenceBundleJob $job): bool => $job->bundleId === $bundle->id);
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/evidence/bundles/{$id}/download")->assertConflict()->assertJsonPath('code', 'evidence.bundle_not_ready');

    app()->call([new BuildEvidenceBundleJob($bundle->id), 'handle']);

    switchTo($assessor)->getJson("/api/v1/assessments/{$assessment->id}/evidence/bundles/{$id}")->assertOk()->assertJsonPath('data.status', 'ready');

    expect(financeNoticeSent($assessor, NotificationCode::EvidenceBundleReady))->toBeTrue();

    $this->travel(25)->hours();
    Artisan::call('moashir:assessments:purge-evidence-bundles');

    expect($bundle->refresh()->status->value)->toBe('expired')
        ->and(StoredFile::withTrashed()->findOrFail($bundle->stored_file_id)->trashed())->toBeTrue();
});

it('refuses an evidence bundle when no clean evidence exists', function (): void {
    $version = publishedAccreditedCriteria();
    $assessor = assessorUser();
    $assessment = assignedAccreditedRequest($version, $assessor);
    EvidenceFile::query()->where('assessment_id', $assessment->id)->update(['status' => 'removed', 'removed_at' => now()]);

    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/evidence/bundles")->assertConflict()->assertJsonPath('code', 'evidence.bundle_empty');
});
