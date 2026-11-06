<?php

declare(strict_types=1);

use App\Actions\Assessments\OpenPaidAccreditedRequest;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Criteria\ScaleKind;
use App\Enums\Identity\RoleKey;
use App\Enums\Integrations\IntegrationMode;
use App\Models\Assessments\Assessment;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Models\Identity\UserRequestPath;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;

const ACCREDITED_CV_TEXT = 'تعتمد الجمعية خطة استراتيجية للفترة 2025 - 2028 أقرها مجلس الإدارة، وتراجع مؤشراتها كل ربع سنة.';

const POLICY_DOCUMENT_KEY = 'policy_document';

const ACCREDITED_FULL_MARKS = ['M1.1' => '8', 'M1.2.1' => '9', 'M1.2.2' => '6', 'M2.1' => '7.5'];

const ASSIGNMENT_REASON = 'انتقل المقيّم السابق إلى مهام مراجعة أخرى بقرار من الإدارة.';

const SUPER_ADMIN_REASON = 'تصحيح إداري معتمد بخطاب رسمي من الجهة بعد مراجعة لجنة الجودة.';

function publishedAccreditedCriteria(ScaleKind $scale = ScaleKind::OutOfTen): CriteriaVersion
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

    return publishVersion($version->refresh(), $scale);
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
