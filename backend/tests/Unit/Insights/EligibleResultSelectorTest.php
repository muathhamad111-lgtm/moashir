<?php

declare(strict_types=1);

use App\Data\Insights\ResultCandidate;
use App\Data\Insights\ResultExclusion;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Certificates\CertificateStatus;
use App\Enums\Insights\ResultIneligibility;
use App\Models\Assessments\Assessment;
use App\Models\Certificates\Certificate;
use App\Support\Insights\EligibleResultSelector;
use App\Support\Insights\ResultEligibility;
use Carbon\CarbonImmutable;

function candidate(int $id, ?string $completedAt, ?CertificateStatus $certificate = null, AssessmentPath $path = AssessmentPath::Accredited, mixed $status = null): ResultCandidate
{
    $assessment = new Assessment;
    $assessment->forceFill([
        'id' => $id,
        'path' => $path,
        'status' => $status ?? ($completedAt === null ? AccreditedAssessmentStatus::UnderReview : AccreditedAssessmentStatus::Completed),
        'current_result_snapshot_id' => $id * 10,
        'completed_at' => $completedAt === null ? null : CarbonImmutable::parse($completedAt),
    ]);

    $issued = null;

    if ($certificate instanceof CertificateStatus) {
        $issued = new Certificate;
        $issued->forceFill(['status' => $certificate]);
    }

    return new ResultCandidate($assessment, $issued);
}

function selector(): EligibleResultSelector
{
    return new EligibleResultSelector(new ResultEligibility);
}

it('selects the latest completed accredited result, newest first with the id breaking ties', function (): void {
    $candidates = [
        candidate(1, '2026-01-01'),
        candidate(2, '2026-06-01'),
        candidate(3, '2026-06-01'),
        candidate(4, '2026-03-01', CertificateStatus::Superseded),
    ];

    expect(array_map(static fn (ResultCandidate $item): int => $item->id(), selector()->eligible($candidates)))->toBe([3, 2, 4, 1])
        ->and(selector()->latest($candidates)?->id())->toBe(3)
        ->and(selector()->latest([]))->toBeNull();
});

it('excludes revoked, suspended, reopened and advanced results and reports the newer exclusions', function (): void {
    $candidates = [
        candidate(1, '2026-01-01', CertificateStatus::Issued),
        candidate(2, '2026-02-01', CertificateStatus::Revoked),
        candidate(3, '2026-03-01', CertificateStatus::Suspended),
        candidate(4, null),
        candidate(5, '2026-04-01', null, AssessmentPath::Advanced, AdvancedAssessmentStatus::AwaitingReviewAssignment),
        candidate(6, '2025-06-01', CertificateStatus::Revoked),
    ];

    $exclusions = selector()->exclusionsNewerThan(selector()->latest($candidates), $candidates);

    expect(selector()->latest($candidates)?->id())->toBe(1)
        ->and(array_map(static fn (ResultExclusion $exclusion): array => [$exclusion->candidate->id(), $exclusion->reason], $exclusions))->toBe([
            [4, ResultIneligibility::UnderReview],
            [3, ResultIneligibility::UnderReview],
            [2, ResultIneligibility::CertificateRevoked],
        ]);
});

it('finds the previous eligible result just before the one shown, skipping exclusions', function (): void {
    $candidates = [
        candidate(1, '2026-01-01'),
        candidate(2, '2026-02-01', CertificateStatus::Revoked),
        candidate(3, '2026-03-01'),
        candidate(4, '2026-04-01'),
    ];

    expect(selector()->previousOf($candidates[3], $candidates)?->id())->toBe(3)
        ->and(selector()->previousOf($candidates[2], $candidates)?->id())->toBe(1)
        ->and(selector()->previousOf($candidates[0], $candidates))->toBeNull();
});

it('names the reason of every ineligible assessment', function (): void {
    $eligibility = new ResultEligibility;
    $draft = new Assessment;
    $draft->forceFill(['path' => AssessmentPath::Accredited, 'status' => AccreditedAssessmentStatus::Draft, 'current_result_snapshot_id' => null]);

    expect($eligibility->reasonOf($draft, null))->toBe(ResultIneligibility::NotCompleted)
        ->and($eligibility->reasonFor(candidate(9, null)))->toBe(ResultIneligibility::UnderReview)
        ->and($eligibility->reasonFor(candidate(9, '2026-01-01', null, AssessmentPath::Advanced, AdvancedAssessmentStatus::AwaitingReviewAssignment)))->toBe(ResultIneligibility::NotAccredited)
        ->and($eligibility->reasonFor(candidate(9, '2026-01-01', CertificateStatus::Revoked)))->toBe(ResultIneligibility::CertificateRevoked)
        ->and($eligibility->reasonFor(candidate(9, '2026-01-01', CertificateStatus::Issued)))->toBeNull()
        ->and($eligibility->reasonFor(candidate(9, '2026-01-01')))->toBeNull();
});
