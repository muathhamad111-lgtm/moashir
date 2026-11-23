<?php

declare(strict_types=1);

use App\Enums\Assessments\AdvancedAssessmentStatus as S;
use App\Enums\Certificates\CertificateKind as K;
use App\Services\Award\AwardCertificateKinds;

it('gives each final status its certificates, with participation only after the initial assessment and only when the cycle gives it', function (S $status, bool $assessed, bool $participation, array $expected): void {
    expect((new AwardCertificateKinds)->for($status, $assessed, $participation))->toBe($expected);
})->with([
    'national winner, participation on' => [S::NationalWinner, true, true, [K::AdvancedWinner, K::AdvancedParticipation]],
    'national winner, participation off' => [S::NationalWinner, true, false, [K::AdvancedWinner]],
    'regional winner not chosen nationally, on' => [S::NationalNonWinner, true, true, [K::AdvancedWinner, K::AdvancedParticipation]],
    'approved non-winner, on' => [S::NonWinner, true, true, [K::AdvancedQualified, K::AdvancedParticipation]],
    'approved non-winner, off' => [S::NonWinner, true, false, [K::AdvancedQualified]],
    'not qualified, on' => [S::NotQualified, true, true, [K::AdvancedParticipation]],
    'not qualified, off' => [S::NotQualified, true, false, []],
    'withdrawn after the initial assessment, on' => [S::Withdrawn, true, true, [K::AdvancedParticipation]],
    'withdrawn after the initial assessment, off' => [S::Withdrawn, true, false, []],
    'withdrawn before the initial assessment, on' => [S::Withdrawn, false, true, []],
    'declined before the initial assessment, on' => [S::DeclinedIncomplete, false, true, []],
    'rejected on conditions, on' => [S::RejectedConditions, false, true, []],
]);

it('names no outcome certificate before the results are final', function (S $status): void {
    expect((new AwardCertificateKinds)->outcomeKindOf($status))->toBeNull();
})->with([S::AwaitingFiltering, S::QualifiedForCommittee, S::AwaitingSecretaryApproval, S::Approved, S::RegionalWinner, S::QualifiedForNational]);
