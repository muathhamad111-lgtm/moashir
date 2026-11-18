<?php

declare(strict_types=1);

namespace App\Services\Certificates;

use App\Data\Certificates\CertificateIssue;
use App\Enums\Certificates\CertificateStatus;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Models\Certificates\Certificate;
use App\Models\Identity\User;

/**
 * Keeps the certificate in force in step with a result the Super Admin overrode (operator decision,
 * 2026-11-18): when the overridden result changes the level or the shown score, the certificate is
 * superseded and reissued from the new result with the override reason. Suspended and revoked
 * certificates are left to their own staff flows.
 */
final readonly class OverriddenResultCertificate
{
    public function __construct(
        private CertificateSettingsReader $settings,
        private CertificateSupersession $supersession,
        private CertificateIssuer $issuer,
    ) {}

    public function reissueFor(User $actor, Assessment $locked, AssessmentResultSnapshot $result, string $reason): ?Certificate
    {
        $inForce = Certificate::query()
            ->where('assessment_id', $locked->id)
            ->where('status', CertificateStatus::Issued)
            ->latest('id')
            ->lockForUpdate()
            ->first();

        if (! $inForce instanceof Certificate || ! $this->changes($inForce, $result)) {
            return null;
        }

        $this->supersession->supersede($inForce);

        return $this->issuer->issue(new CertificateIssue($locked, $result, $actor->id, $inForce, $reason));
    }

    private function changes(Certificate $inForce, AssessmentResultSnapshot $result): bool
    {
        return $inForce->level !== $this->settings->thresholds()->levelFor($result->percent)
            || $inForce->score_percent->toDisplay() !== $result->percent->toDisplay();
    }
}
