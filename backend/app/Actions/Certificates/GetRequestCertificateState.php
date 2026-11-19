<?php

declare(strict_types=1);

namespace App\Actions\Certificates;

use App\Data\Certificates\RequestCertificateState;
use App\Enums\Certificates\CertificateKind;
use App\Enums\Certificates\CertificateStatus;
use App\Models\Assessments\Assessment;
use App\Models\Certificates\Certificate;
use App\Services\Certificates\CertificateSettingsReader;

/**
 * What the assigned assessor needs on the request page: the current certificate (if any) and
 * whether the platform issues it automatically or waits for the assessor.
 */
final readonly class GetRequestCertificateState
{
    public function __construct(private CertificateSettingsReader $settings) {}

    public function handle(Assessment $assessment): RequestCertificateState
    {
        $current = Certificate::query()
            ->where('assessment_id', $assessment->id)
            ->where('kind', CertificateKind::Accredited)
            ->whereIn('status', [CertificateStatus::Issued, CertificateStatus::Suspended])
            ->with(ListCertificates::RELATIONS)
            ->latest('id')
            ->first();

        return new RequestCertificateState($current, $this->settings->accreditedIssuanceMode());
    }
}
