<?php

declare(strict_types=1);

namespace App\Support\Insights;

use App\Data\Insights\ResultCandidate;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Certificates\CertificateStatus;
use App\Enums\Insights\ResultIneligibility;
use App\Models\Assessments\Assessment;
use App\Models\Certificates\Certificate;

final class ResultEligibility
{
    public function reasonFor(ResultCandidate $candidate): ?ResultIneligibility
    {
        return $this->reasonOf($candidate->assessment, $candidate->certificate);
    }

    public function reasonOf(Assessment $assessment, ?Certificate $latestCertificate): ?ResultIneligibility
    {
        if (! $assessment->isAccredited()) {
            return ResultIneligibility::NotAccredited;
        }

        if ($assessment->status !== AccreditedAssessmentStatus::Completed || ! $assessment->hasResult()) {
            return $assessment->hasResult() ? ResultIneligibility::UnderReview : ResultIneligibility::NotCompleted;
        }

        return match ($latestCertificate?->status) {
            CertificateStatus::Revoked => ResultIneligibility::CertificateRevoked,
            CertificateStatus::Suspended => ResultIneligibility::UnderReview,
            default => null,
        };
    }
}
