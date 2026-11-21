<?php

declare(strict_types=1);

namespace App\Services\Certificates;

use App\Contracts\Assessments\AssessmentReopenStep;
use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Certificates\CertificateKind;
use App\Enums\Certificates\CertificateStatus;
use App\Events\Certificates\CertificateSuspended;
use App\Models\Assessments\Assessment;
use App\Models\Certificates\Certificate;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\Date;

final readonly class CertificateSuspender implements AssessmentReopenStep
{
    public function __construct(private AuditRecorder $audit) {}

    public function onReopen(Assessment $assessment, string $reason): void
    {
        $certificate = Certificate::query()
            ->where('assessment_id', $assessment->id)
            ->where('kind', CertificateKind::Accredited)
            ->where('status', CertificateStatus::Issued)
            ->lockForUpdate()
            ->first();

        if (! $certificate instanceof Certificate) {
            return;
        }

        $certificate->forceFill([
            'status' => CertificateStatus::Suspended,
            'suspended_at' => Date::now(),
            'suspended_reason' => $reason,
        ])->save();

        $this->audit->record(new AuditEntry(AuditEvent::CertificateSuspended, $certificate, ['status' => CertificateStatus::Issued->value], [
            'status' => CertificateStatus::Suspended->value,
            'number' => $certificate->number,
        ]));

        CertificateSuspended::dispatch($certificate);
    }
}
