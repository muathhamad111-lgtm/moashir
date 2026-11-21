<?php

declare(strict_types=1);

use App\Actions\Assessments\OpenPaidAccreditedRequest;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Assessments\ScoreLevel;
use App\Enums\Criteria\ScaleKind;
use App\Enums\Identity\RoleKey;
use App\Enums\Integrations\IntegrationMode;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentCompletionRequest;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Models\Identity\UserRequestPath;
use App\Services\Assessments\ScoringTreeBuilder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Testing\TestResponse;

const ACCREDITED_CV_TEXT = 'تعتمد الجمعية خطة استراتيجية للفترة 2025 - 2028 أقرها مجلس الإدارة، وتراجع مؤشراتها كل ربع سنة.';

const POLICY_DOCUMENT_KEY = 'policy_document';

const ACCREDITED_FULL_MARKS = ['M1.1' => '8', 'M1.2.1' => '9', 'M1.2.2' => '6', 'M2.1' => '7.5'];

const ASSIGNMENT_REASON = 'انتقل المقيّم السابق إلى مهام مراجعة أخرى بقرار من الإدارة.';

const SUPER_ADMIN_REASON = 'تصحيح إداري معتمد بخطاب رسمي من الجهة بعد مراجعة لجنة الجودة.';

function publishedAccreditedCriteria(ScaleKind $scale = ScaleKind::OutOfTen): CriteriaVersion
{
    return publishVersion(draftAccreditedCriteria(), $scale);
}

/**
 * The accredited test tree as a draft: M1 (M1.1 direct with two attachments, M1.2 on indicators M1.2.1 / M1.2.2),
 * M2 (M2.1 direct without CV, M2.2 inactive).
 */
function draftAccreditedCriteria(): CriteriaVersion
{
    $version = draftVersion();
    $leadership = mainIn($version, 'M1', '1000.00');
    $policy = directSubIn($leadership, 'M1.1', '600.00');
    $strategy = subIn($leadership, 'M1.2', '400.00', 2);
    pointIn($strategy, 'M1.2.1', '200.00');
    pointIn($strategy, 'M1.2.2', '200.00', 2);
    $results = mainIn($version, 'M2', '500.00', 2);
    directSubIn($results, 'M2.1', '500.00')->forceFill(['has_cv' => false])->save();
    directSubIn($results, 'M2.2', '100.00', 2)->forceFill(['is_active' => false])->save();

    $policy->attachmentRequirements()->create([
        'key' => POLICY_DOCUMENT_KEY,
        'name' => ['ar' => 'وثيقة السياسة المعتمدة', 'en' => 'Approved policy document'],
        'is_required' => true,
        'sort_order' => 1,
    ]);
    $policy->attachmentRequirements()->create([
        'key' => 'board_minutes',
        'name' => ['ar' => 'محضر اعتماد مجلس الإدارة', 'en' => 'Board approval minutes'],
        'is_required' => false,
        'sort_order' => 2,
    ]);

    return $version->refresh();
}

function openedAccreditedRequest(?Entity $entity = null): Assessment
{
    $payment = storedPaidPayment($entity ?? entityWithOwner(), IntegrationMode::Live);
    $assessment = app(OpenPaidAccreditedRequest::class)->handle($payment->id);

    return $assessment instanceof Assessment ? $assessment->refresh() : throw new LogicException('The accredited request was not opened.');
}

/**
 * @return array<string, string>
 */
function accreditedCv(CriteriaVersion $version): array
{
    return [
        (string) subOf($version, 'M1.1')->id => ACCREDITED_CV_TEXT,
        (string) subOf($version, 'M1.2')->id => 'أعدت الجمعية خطتها التشغيلية بمشاركة المستفيدين والشركاء.',
    ];
}

function evidencePdf(string $name = 'سياسة-الحوكمة.pdf', string $contents = SAMPLE_PDF): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $contents);
}

function uploadEvidence(User $owner, Assessment $assessment, CriteriaVersion $version, string $code = 'M1.1', ?string $key = POLICY_DOCUMENT_KEY, ?UploadedFile $file = null): TestResponse
{
    return switchTo($owner)->post("/api/v1/assessments/{$assessment->id}/evidence", array_filter([
        'file' => $file ?? evidencePdf(),
        'sub_criterion_id' => subOf($version, $code)->id,
        'attachment_key' => $key,
    ], static fn (mixed $value): bool => $value !== null), ['Accept' => 'application/json']);
}

function readyAccreditedDraft(CriteriaVersion $version, ?Entity $entity = null): Assessment
{
    $assessment = openedAccreditedRequest($entity);
    $owner = ownerOf($assessment->entity);

    switchTo($owner)->putJson("/api/v1/assessments/{$assessment->id}/cv", ['cv' => accreditedCv($version)])->assertOk();
    uploadEvidence($owner, $assessment, $version)->assertCreated();

    return $assessment->refresh();
}

function submittedAccreditedRequest(CriteriaVersion $version, ?Entity $entity = null): Assessment
{
    $assessment = readyAccreditedDraft($version, $entity);

    switchTo(ownerOf($assessment->entity))->postJson("/api/v1/assessments/{$assessment->id}/submit")->assertOk()->assertJsonPath('data.status', 'submitted');

    return $assessment->refresh();
}

function assessorUser(): User
{
    return staffUser(RoleKey::Assessor);
}

function requestFollower(): User
{
    return staffUser(RoleKey::RequestFollower);
}

function followerOfPaths(AssessmentPath ...$paths): User
{
    $follower = requestFollower();

    foreach ($paths as $path) {
        UserRequestPath::query()->create(['user_id' => $follower->id, 'path' => $path]);
    }

    return $follower->refresh();
}

function assignedAccreditedRequest(CriteriaVersion $version, User $assessor, ?User $follower = null): Assessment
{
    $assessment = submittedAccreditedRequest($version);

    switchTo($follower ?? requestFollower())->postJson("/api/v1/requests/{$assessment->id}/assign", ['assessor_id' => $assessor->id])->assertOk();

    return $assessment->refresh();
}

function reviewedAccreditedRequest(CriteriaVersion $version, User $assessor): Assessment
{
    $assessment = assignedAccreditedRequest($version, $assessor);

    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/start-review")->assertOk()->assertJsonPath('data.status', 'under_review');

    return $assessment->refresh();
}

/**
 * @return array<string, array{strengths: string, improvement_points: string, notes: string}>
 */
function accreditedFeedback(CriteriaVersion $version): array
{
    $feedback = [];

    foreach (['M1.1', 'M1.2', 'M2.1'] as $code) {
        $feedback[(string) subOf($version, $code)->id] = [
            'strengths' => 'وضوح السياسات المعتمدة وربطها بالأهداف الاستراتيجية.',
            'improvement_points' => 'توثيق نتائج المراجعة الدورية ونشرها لأصحاب العلاقة.',
            'notes' => 'الشواهد المرفوعة كافية لدعم التقييم.',
        ];
    }

    return $feedback;
}

function scoredAccreditedReview(CriteriaVersion $version, User $assessor): Assessment
{
    $assessment = reviewedAccreditedRequest($version, $assessor);

    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/scores", ['scores' => selfScores($version, ACCREDITED_FULL_MARKS)])->assertOk();
    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/feedback", ['feedback' => accreditedFeedback($version)])->assertOk();

    return $assessment->refresh();
}

function completedAccreditedRequest(CriteriaVersion $version, User $assessor): Assessment
{
    $assessment = scoredAccreditedReview($version, $assessor);

    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/complete")->assertOk()->assertJsonPath('data.status', 'completed');

    return $assessment->refresh();
}

/**
 * Scores every unit of the request's criteria version through the review endpoint: a completion request needs a
 * fully scored review (operator decision UX-1). One is valid for every scoring type.
 */
function scoreEveryUnit(User $assessor, Assessment $assessment, string $raw = '1'): void
{
    $scores = ['subs' => [], 'points' => []];

    foreach (app(ScoringTreeBuilder::class)->build($assessment->refresh()->criteriaVersion)->scorableUnits() as $unit) {
        $scores[$unit->level === ScoreLevel::Sub ? 'subs' : 'points'][(string) $unit->id] = $raw;
    }

    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/scores", ['scores' => $scores])->assertOk();
}

/**
 * @return array<string, mixed>
 */
function roundCompletionPayload(CriteriaVersion $version): array
{
    return [
        'notes' => 'يرجى تحديث وصف السياسة المعتمدة.',
        'items' => [['sub_criterion_id' => subOf($version, 'M1.1')->id, 'kind' => 'cv', 'note' => 'لم تُرفق الخطة المعتمدة']],
        'deadline_at' => Date::now()->addDays(7)->toIso8601String(),
        'expiry_outcome' => 'decline',
    ];
}

/**
 * @param  array<string, string>  $marks
 */
function saveRoundScores(User $assessor, Assessment $assessment, CriteriaVersion $version, array $marks): void
{
    switchTo($assessor)->putJson("/api/v1/assessments/{$assessment->id}/review/scores", ['scores' => selfScores($version, $marks)])->assertOk();
}

/**
 * A request scored in full (round 1), returned for completion and resubmitted by the entity: round 2 is open.
 *
 * @return array{0: Assessment, 1: AssessmentCompletionRequest}
 */
function resubmittedForRoundTwo(CriteriaVersion $version, User $assessor): array
{
    $assessment = reviewedAccreditedRequest($version, $assessor);
    saveRoundScores($assessor, $assessment, $version, ACCREDITED_FULL_MARKS);
    switchTo($assessor)->postJson("/api/v1/assessments/{$assessment->id}/request-completion", roundCompletionPayload($version))->assertOk();

    $owner = ownerOf($assessment->entity);
    switchTo($owner)->putJson("/api/v1/assessments/{$assessment->id}/cv", ['cv' => [(string) subOf($version, 'M1.1')->id => 'وصف محدث للسياسة المعتمدة.']])->assertOk();
    switchTo($owner)->postJson("/api/v1/assessments/{$assessment->id}/submit")->assertOk()->assertJsonPath('data.status', 'under_review');

    return [$assessment->refresh(), AssessmentCompletionRequest::query()->where('assessment_id', $assessment->id)->sole()];
}
