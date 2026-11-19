<?php

declare(strict_types=1);

namespace App\Actions\Certificates;

use App\Models\Certificates\Certificate;
use App\Models\Identity\User;
use App\Services\Assessments\StaffActionReason;
use App\Services\Certificates\CertificateReplacement;

final readonly class ReissueCertificate
{
    public function __construct(private StaffActionReason $reasons, private CertificateReplacement $replacement) {}

    public function handle(User $actor, Certificate $certificate, string $reason): Certificate
    {
        $this->reasons->record($actor, $reason);

        return $this->replacement->replace($certificate, $actor->id, $reason);
    }
}
