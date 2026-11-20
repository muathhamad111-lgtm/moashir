<?php

declare(strict_types=1);

use App\Enums\Files\MalwareScanStatus;
use App\Enums\Settings\SettingKey;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\EvidenceFile;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Identity\User;
use App\Notifications\Entities\EntityNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

const MAIN_PLAN_KEY = 'main_plan';

const MAIN_PHOTOS_KEY = 'main_photos';

const INDICATOR_SURVEY_KEY = 'indicator_survey';

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
});

/**
 * The accredited tree with attachments at the other two levels: M1 asks for its plan (PDF only, one file, required)
 * and photos (PNG only, optional); the indicator M1.2.1 asks for a survey (required).
 */
function criteriaWithMainAttachments(): CriteriaVersion
{
    $version = draftAccreditedCriteria();
    $main = mainOf($version, 'M1');
    $main->attachmentRequirements()->create([
        'key' => MAIN_PLAN_KEY,
        'name' => ['ar' => 'الخطة الاستراتيجية المعتمدة', 'en' => 'Approved strategic plan'],
        'guidance' => ['ar' => 'ارفع الخطة موقّعة من مجلس الإدارة.', 'en' => 'Upload the plan signed by the board.'],
        'allowed_types' => ['pdf'],
        'max_files' => 1,
        'is_required' => true,
        'sort_order' => 1,
    ]);
    $main->attachmentRequirements()->create([
        'key' => MAIN_PHOTOS_KEY,
        'name' => ['ar' => 'صور الفعاليات', 'en' => 'Event photos'],
        'allowed_types' => ['png'],
        'is_required' => false,
        'sort_order' => 2,
    ]);
    pointOf($version, 'M1.2.1')->attachmentRequirements()->create([
        'key' => INDICATOR_SURVEY_KEY,
        'name' => ['ar' => 'نتائج استبيان المستفيدين', 'en' => 'Beneficiary survey results'],
        'is_required' => true,
        'sort_order' => 1,
    ]);

    return publishVersion($version->refresh());
}

function uploadMainEvidence(User $owner, Assessment $assessment, CriteriaVersion $version, ?string $key = MAIN_PLAN_KEY, ?UploadedFile $file = null): TestResponse
{
    return switchTo($owner)->post("/api/v1/assessments/{$assessment->id}/evidence", array_filter([
        'file' => $file ?? evidencePdf('الخطة.pdf'),
        'main_criterion_id' => mainOf($version, 'M1')->id,
        'attachment_key' => $key,
    ], static fn (mixed $value): bool => $value !== null), ['Accept' => 'application/json']);
}

/**
 * A submitted request whose version later gained the attachments of {@see criteriaWithMainAttachments()}, as a
 * criteria edit rebinds the open requests.
 */
function requestThatGainedAttachments(User $assessor): array
{
    $before = publishedAccreditedCriteria();
    $assessment = reviewedAccreditedRequest($before, $assessor);
    archiveVersion($before);
    $after = criteriaWithMainAttachments();
    $assessment->forceFill(['criteria_version_id' => $after->id])->save();

    return [$assessment->refresh(), $after];
}

function requestCompletionOf(User $assessor, Assessment $assessment, array $items): TestResponse
{
    return switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/request-completion", [
        'notes' => 'يرجى استكمال المرفقات المطلوبة المضافة بعد التقديم.',
        'items' => $items,
        'deadline_at' => now()->addDays(5)->toIso8601String(),
        'expiry_outcome' => 'continue_as_submitted',
    ]);
}

it('stores a main criterion attachment under the main criterion and lists it with its level', function (): void {
    $version = criteriaWithMainAttachments();
    $assessment = openedAccreditedRequest();
    $owner = ownerOf($assessment->entity);

    $response = uploadMainEvidence($owner, $assessment, $version)->assertCreated()
        ->assertJsonPath('data.level', 'main')
        ->assertJsonPath('data.main_criterion_id', mainOf($version, 'M1')->id)
        ->assertJsonPath('data.sub_criterion_id', null)
        ->assertJsonPath('data.code', 'M1')
        ->assertJsonPath('data.attachment_key', MAIN_PLAN_KEY);

    $evidence = EvidenceFile::query()->where('public_id', $response->json('data.id'))->firstOrFail();

    expect($evidence->main_code)->toBe('M1')->and($evidence->sub_code)->toBeNull();

    switchTo($owner)->getJson("/api/v1/assessments/{$assessment->id}/form")->assertOk()
        ->assertJsonPath('data.evidence.0.level', 'main')
        ->assertJsonPath('data.criteria.mains.0.attachments.0.guidance.en', 'Upload the plan signed by the board.');
});

it('keeps an indicator attachment under its sub-criterion', function (): void {
    $version = criteriaWithMainAttachments();
    $assessment = openedAccreditedRequest();

    uploadEvidence(ownerOf($assessment->entity), $assessment, $version, 'M1.2', INDICATOR_SURVEY_KEY)->assertCreated()
        ->assertJsonPath('data.level', 'sub')
        ->assertJsonPath('data.sub_criterion_id', subOf($version, 'M1.2')->id)
        ->assertJsonPath('data.main_criterion_id', null);
});

it('validates the target of a main criterion upload', function (): void {
    $version = criteriaWithMainAttachments();
    $assessment = openedAccreditedRequest();
    $owner = ownerOf($assessment->entity);
    $url = "/api/v1/assessments/{$assessment->id}/evidence";

    uploadMainEvidence($owner, $assessment, $version, null)->assertUnprocessable()->assertJsonValidationErrors(['attachment_key']);
    uploadMainEvidence($owner, $assessment, $version, POLICY_DOCUMENT_KEY)->assertUnprocessable()->assertJsonPath('code', 'attachment.key_invalid');
    switchTo($owner)->post($url, ['file' => evidencePdf(), 'main_criterion_id' => 999_999, 'attachment_key' => MAIN_PLAN_KEY], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonPath('code', 'assessment.main_not_in_version');
    switchTo($owner)->post($url, ['file' => evidencePdf(), 'main_criterion_id' => mainOf($version, 'M1')->id, 'sub_criterion_id' => subOf($version, 'M1.1')->id, 'attachment_key' => MAIN_PLAN_KEY], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors(['sub_criterion_id']);
    switchTo($owner)->post($url, ['file' => evidencePdf()], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors(['sub_criterion_id', 'main_criterion_id']);

    expect(EvidenceFile::query()->count())->toBe(0);
});

it('keeps each attachment to its own file types and file count within the path settings', function (): void {
    $version = criteriaWithMainAttachments();
    $assessment = openedAccreditedRequest();
    $owner = ownerOf($assessment->entity);
    setIntegrationSetting(SettingKey::AccreditedEvidenceAllowedTypes, ['pdf', 'png']);

    uploadMainEvidence($owner, $assessment, $version, MAIN_PLAN_KEY, pngImage('فعالية.png'))->assertUnprocessable()->assertJsonPath('code', 'file.content_mismatch');
    uploadMainEvidence($owner, $assessment, $version, MAIN_PHOTOS_KEY)->assertUnprocessable()->assertJsonPath('code', 'file.content_mismatch');
    uploadMainEvidence($owner, $assessment, $version, MAIN_PHOTOS_KEY, pngImage('فعالية.png'))->assertCreated();
    uploadMainEvidence($owner, $assessment, $version)->assertCreated();
    uploadMainEvidence($owner, $assessment, $version)->assertUnprocessable()
        ->assertJsonPath('code', 'attachment.slot_full')
        ->assertJsonPath('meta.max_files', 1);
});

it('falls back to the path types when the path no longer allows the attachment types', function (): void {
    $version = criteriaWithMainAttachments();
    $assessment = openedAccreditedRequest();
    setIntegrationSetting(SettingKey::AccreditedEvidenceAllowedTypes, ['pdf']);

    uploadMainEvidence(ownerOf($assessment->entity), $assessment, $version, MAIN_PHOTOS_KEY)->assertCreated();
});

it('stops a main criterion at the path file maximum', function (): void {
    $version = criteriaWithMainAttachments();
    $assessment = openedAccreditedRequest();
    $owner = ownerOf($assessment->entity);
    setIntegrationSetting(SettingKey::AccreditedEvidenceAllowedTypes, ['pdf', 'png']);
    setIntegrationSetting(SettingKey::AccreditedEvidenceMaxFilesPerSub, 1);

    uploadMainEvidence($owner, $assessment, $version, MAIN_PHOTOS_KEY, pngImage('فعالية.png'))->assertCreated();
    uploadMainEvidence($owner, $assessment, $version)->assertUnprocessable()
        ->assertJsonPath('code', 'attachment.main_full')
        ->assertJsonPath('meta.max_files_per_sub', 1);
});

it('blocks the submission until the required attachments of every level are uploaded', function (): void {
    $version = criteriaWithMainAttachments();
    $assessment = readyAccreditedDraft($version);
    $owner = ownerOf($assessment->entity);
    $main = mainOf($version, 'M1');

    switchTo($owner)->postJson("/api/v1/assessments/{$assessment->id}/submit")->assertUnprocessable()
        ->assertJsonPath('code', 'submission.incomplete')
        ->assertJsonValidationErrors(["main_attachments.{$main->id}.".MAIN_PLAN_KEY, 'attachments.'.subOf($version, 'M1.2')->id.'.'.INDICATOR_SURVEY_KEY])
        ->assertJsonPath('meta.missing_main_attachments', [['main_criterion_id' => $main->id, 'code' => 'M1', 'attachment_key' => MAIN_PLAN_KEY]]);

    uploadMainEvidence($owner, $assessment, $version)->assertCreated();
    uploadEvidence($owner, $assessment, $version, 'M1.2', INDICATOR_SURVEY_KEY)->assertCreated();

    switchTo($owner)->postJson("/api/v1/assessments/{$assessment->id}/submit")->assertOk()->assertJsonPath('data.status', 'submitted');
});

it('marks the required attachments that came after the submission for the assessor', function (): void {
    $assessor = assessorUser();
    [$assessment, $version] = requestThatGainedAttachments($assessor);

    switchTo($assessor)->getJson("/api/v1/assessments/{$assessment->id}/review")->assertOk()
        ->assertJsonPath('data.late_attachment_keys', [MAIN_PLAN_KEY, INDICATOR_SURVEY_KEY]);
});

it('does not mark an attachment whose file was refused after the submission', function (): void {
    $version = criteriaWithMainAttachments();
    $assessor = assessorUser();
    $assessment = openedAccreditedRequest();
    $owner = ownerOf($assessment->entity);
    switchTo($owner)->putJson("/api/v1/assessments/{$assessment->id}/cv", ['cv' => accreditedCv($version)])->assertOk();
    uploadEvidence($owner, $assessment, $version)->assertCreated();
    uploadMainEvidence($owner, $assessment, $version)->assertCreated();
    uploadEvidence($owner, $assessment, $version, 'M1.2', INDICATOR_SURVEY_KEY)->assertCreated();
    switchTo($owner)->postJson("/api/v1/assessments/{$assessment->id}/submit")->assertOk();
    EvidenceFile::query()->where('main_code', 'M1')->firstOrFail()->storedFile->forceFill(['malware_scan_status' => MalwareScanStatus::Infected])->save();
    switchTo(requestFollower())->postJson("/api/v1/requests/{$assessment->id}/assign", ['assessor_id' => $assessor->id])->assertOk();

    switchTo($assessor)->getJson("/api/v1/assessments/{$assessment->id}/review")->assertOk()->assertJsonPath('data.late_attachment_keys', []);
});

it('lets the assessor ask for the attachments of a main criterion and the entity upload only those', function (): void {
    $assessor = assessorUser();
    [$assessment, $version] = requestThatGainedAttachments($assessor);
    $owner = ownerOf($assessment->entity);
    $main = mainOf($version, 'M1');

    requestCompletionOf($assessor, $assessment, [['main_criterion_id' => $main->id, 'kind' => 'evidence', 'note' => 'الخطة المعتمدة']])->assertOk()
        ->assertJsonPath('data.status', 'needs_completion');

    switchTo($owner)->getJson("/api/v1/assessments/{$assessment->id}/form")->assertOk()
        ->assertJsonPath('data.completion_request.items.0.main_criterion_id', $main->id)
        ->assertJsonPath('data.completion_request.items.0.sub_criterion_id', null)
        ->assertJsonPath('data.completion_request.items.0.code', 'M1')
        ->assertJsonPath('data.completion_request.items.0.name.ar', $main->name->ar)
        ->assertJsonPath('data.missing.missing_attachments', [])
        ->assertJsonPath('data.missing.missing_main_attachments.0.attachment_key', MAIN_PLAN_KEY);

    Notification::assertSentTo($owner, EntityNotification::class, static function (EntityNotification $notification) use ($main): bool {
        $item = $notification->notice->blocks->items?->items[0] ?? null;

        return $item?->code === 'M1' && $item->name?->in('ar') === $main->name->ar;
    });
    uploadEvidence($owner, $assessment, $version, 'M1.1', 'board_minutes')->assertUnprocessable()->assertJsonPath('code', 'completion.item_not_requested');
    switchTo($owner)->postJson("/api/v1/assessments/{$assessment->id}/submit")->assertUnprocessable()->assertJsonPath('code', 'submission.incomplete');
    uploadMainEvidence($owner, $assessment, $version)->assertCreated();
    switchTo($owner)->postJson("/api/v1/assessments/{$assessment->id}/submit")->assertOk()->assertJsonPath('data.status', 'under_review');
});

it('resubmits a completion when only the requested items are complete', function (): void {
    $assessor = assessorUser();
    [$assessment, $version] = requestThatGainedAttachments($assessor);
    $owner = ownerOf($assessment->entity);

    requestCompletionOf($assessor, $assessment, [['sub_criterion_id' => subOf($version, 'M1.1')->id, 'kind' => 'cv']])->assertOk();
    switchTo($owner)->putJson("/api/v1/assessments/{$assessment->id}/cv", ['cv' => [(string) subOf($version, 'M1.1')->id => ACCREDITED_CV_TEXT.' تحديث.']])->assertOk();

    switchTo($owner)->postJson("/api/v1/assessments/{$assessment->id}/submit")->assertOk()->assertJsonPath('data.status', 'under_review');
});

it('refuses a completion item that asks a main criterion for anything but its attachments', function (): void {
    $assessor = assessorUser();
    [$assessment, $version] = requestThatGainedAttachments($assessor);

    requestCompletionOf($assessor, $assessment, [['main_criterion_id' => mainOf($version, 'M1')->id, 'kind' => 'cv']])->assertUnprocessable()
        ->assertJsonPath('code', 'cv.not_allowed');
    requestCompletionOf($assessor, $assessment, [['main_criterion_id' => 999_999, 'kind' => 'evidence']])->assertUnprocessable()
        ->assertJsonPath('code', 'assessment.main_not_in_version');
    requestCompletionOf($assessor, $assessment, [['main_criterion_id' => mainOf($version, 'M1')->id, 'sub_criterion_id' => subOf($version, 'M1.1')->id, 'kind' => 'evidence']])->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.sub_criterion_id']);
});
