<?php

declare(strict_types=1);

use App\Actions\Certificates\IssueAccreditedCertificate;
use App\Actions\Certificates\RevokeCertificate;
use App\Data\Assessments\ScoredUnit;
use App\Data\Assessments\ScoreSheet;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Assessments\FeedbackKind;
use App\Enums\Assessments\ResultSnapshotKind;
use App\Enums\Assessments\SelfAssessmentStatus;
use App\Enums\Audit\AuditEvent;
use App\Enums\Criteria\ScaleKind;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Settings\SettingKey;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentFeedbackItem;
use App\Models\Assessments\AssessmentSummary;
use App\Models\Certificates\Certificate;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Assessments\ResultSnapshotRecorder;
use App\Services\Assessments\ScoringEngine;
use App\Services\Assessments\ScoringTreeBuilder;
use App\Services\Settings\SettingsStore;
use App\Support\ValueObjects\RawScore;
use Carbon\CarbonImmutable;

const INSIGHTS_TREE = ['M1' => ['M1.1' => '60.00', 'M1.2' => '40.00'], 'M2' => ['M2.1' => '50.00', 'M2.2' => '50.00']];

const REVOKE_REASON = 'أُلغيت الشهادة بعد ثبوت خطأ جوهري في الشواهد المقدمة من الجهة.';

/**
 * @param  array<string, array<string, string>>  $tree
 */
function insightsCriteria(array $tree = INSIGHTS_TREE): CriteriaVersion
{
    $version = draftVersion(['label' => ['ar' => 'النسخة التجريبية', 'en' => 'Test version']]);
    $mainOrder = 1;

    foreach ($tree as $mainCode => $subs) {
        $weight = number_format(array_sum(array_map(floatval(...), $subs)), 2, '.', '');
        $main = mainIn($version, $mainCode, $weight, $mainOrder++);
        $main->forceFill(['description' => ['ar' => 'يقيس '.$mainCode, 'en' => 'Measures '.$mainCode]])->save();
        $subOrder = 1;

        foreach ($subs as $subCode => $subWeight) {
            directSubIn($main, $subCode, $subWeight, $subOrder++);
        }
    }

    return publishVersion($version->refresh(), ScaleKind::Relative);
}

/**
 * @param  array<string, string>  $percents
 */
function accreditedResultFor(Entity $entity, CriteriaVersion $version, array $percents, string $completedAt = '2026-06-01 09:00:00'): Assessment
{
    $assessment = insightsAssessment($entity, $version, AssessmentPath::Accredited, AccreditedAssessmentStatus::Submitted);
    recordInsightsSnapshot($assessment, $version, $percents);
    $assessment->forceFill(['status' => AccreditedAssessmentStatus::Completed, 'completed_at' => CarbonImmutable::parse($completedAt)])->save();

    return $assessment->refresh();
}

function insightsAssessment(Entity $entity, CriteriaVersion $version, AssessmentPath $path, mixed $status): Assessment
{
    $payment = $path === AssessmentPath::Accredited ? storedPaidPayment($entity, IntegrationMode::Live) : null;

    $assessment = Assessment::factory()->forEntity($entity)->boundTo($version)->make([
        'request_number' => sprintf('%s-2026-%06d', $path->numberPrefix(), fake()->unique()->numberBetween(1, 899_999)),
        'path' => $path,
        'status' => $status,
        'payment_id' => $payment?->id,
    ]);
    $assessment->forceFill(['submitted_at' => CarbonImmutable::parse('2026-05-01 09:00:00')])->save();

    return $assessment;
}

/**
 * @param  array<string, string>  $percents
 */
function recordInsightsSnapshot(Assessment $assessment, CriteriaVersion $version, array $percents): void
{
    $tree = app(ScoringTreeBuilder::class)->build($version);
    $units = [];

    foreach ($percents as $code => $percent) {
        $unit = $tree->unitWithCode($code) ?? throw new LogicException("Unknown unit {$code}.");
        $units[] = new ScoredUnit($unit, RawScore::on(ScaleKind::Relative, $percent));
    }

    app(ResultSnapshotRecorder::class)->record($assessment, app(ScoringEngine::class)->compute($tree, ScoreSheet::of($units)), ResultSnapshotKind::AccreditedResult, null);
    $assessment->save();
}

function selfResultFor(Entity $entity, CriteriaVersion $version): Assessment
{
    $assessment = insightsAssessment($entity, $version, AssessmentPath::SelfAssessment, SelfAssessmentStatus::Submitted);
    recordInsightsSnapshot($assessment, $version, ['M1.1' => '90.00', 'M1.2' => '90.00', 'M2.1' => '90.00', 'M2.2' => '90.00']);
    $assessment->forceFill(['status' => SelfAssessmentStatus::ResultAvailable, 'completed_at' => CarbonImmutable::parse('2026-07-01 09:00:00')])->save();

    return $assessment->refresh();
}

function certifyResult(Assessment $assessment): Certificate
{
    return app(IssueAccreditedCertificate::class)->handle($assessment, null);
}

function revokeResultCertificate(Assessment $assessment): Certificate
{
    return app(RevokeCertificate::class)->handle(systemAdmin(), certifyResult($assessment), REVOKE_REASON);
}

/**
 * @param  array<string, array<string, string>>  $bodies
 */
function writeFeedbackFor(Assessment $assessment, array $bodies, ?User $author = null): void
{
    $author ??= assessorUser();

    foreach ($bodies as $code => $kinds) {
        foreach ($kinds as $kind => $body) {
            AssessmentFeedbackItem::query()->create([
                'assessment_id' => $assessment->id,
                'sub_code' => $code,
                'kind' => FeedbackKind::from($kind),
                'body' => $body,
                'author_user_id' => $author->id,
                'version' => 1,
            ]);
        }
    }
}

function writeAssessorSummary(Assessment $assessment, string $body): AssessmentSummary
{
    return AssessmentSummary::query()->create([
        'assessment_id' => $assessment->id,
        'body' => $body,
        'author_user_id' => assessorUser()->id,
        'version' => 1,
    ]);
}

/**
 * @param  array<string, string|int>  $gaps
 * @param  array<string, string|int>  $bands
 */
function setInsightsRules(array $gaps = [], array $bands = []): void
{
    if ($gaps !== []) {
        app(SettingsStore::class)->put(SettingKey::InsightsGapThresholds, $gaps, AuditEvent::InsightsSettingsUpdated, null);
    }

    if ($bands !== []) {
        app(SettingsStore::class)->put(SettingKey::InsightsMaturityBands, $bands, AuditEvent::InsightsSettingsUpdated, null);
    }
}

function insightsStaff(): User
{
    return systemAdmin();
}
