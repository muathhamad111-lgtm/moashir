<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Certificates\CertificateKind;

/**
 * Which award certificates a request earns (operator, 2026-11-22):
 * - a participation certificate once its initial assessment is complete, when the cycle's participation setting is
 *   on, whatever came after (withdrawn, not qualified, winner…);
 * - at announcement, a winner certificate for a regional or national winner and a qualified certificate for an
 *   approved non-winner, in addition to the participation certificate.
 */
final class AwardCertificateKinds
{
    /**
     * @return list<CertificateKind>
     */
    public function for(AdvancedAssessmentStatus $status, bool $initialAssessmentComplete, bool $participationEnabled): array
    {
        $outcome = $this->outcomeKindOf($status);
        $kinds = $this->participationApplies($initialAssessmentComplete, $participationEnabled) ? [CertificateKind::AdvancedParticipation] : [];

        return $outcome instanceof CertificateKind ? [$outcome, ...$kinds] : $kinds;
    }

    public function participationApplies(bool $initialAssessmentComplete, bool $participationEnabled): bool
    {
        return $initialAssessmentComplete && $participationEnabled;
    }

    public function outcomeKindOf(AdvancedAssessmentStatus $status): ?CertificateKind
    {
        return match ($status) {
            AdvancedAssessmentStatus::NationalWinner, AdvancedAssessmentStatus::NationalNonWinner => CertificateKind::AdvancedWinner,
            AdvancedAssessmentStatus::NonWinner => CertificateKind::AdvancedQualified,
            default => null,
        };
    }
}
