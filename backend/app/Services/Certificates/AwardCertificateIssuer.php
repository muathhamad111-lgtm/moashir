<?php

declare(strict_types=1);

namespace App\Services\Certificates;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Certificates\CertificateKind;
use App\Enums\Certificates\CertificateStatus;
use App\Events\Certificates\CertificateIssued;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Models\Certificates\Certificate;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\Date;

/**
 * Creates one award certificate row. The row points at the initial-assessment result because every certificate keeps
 * the result it was issued on; that score stays internal and is never shown on an award certificate.
 */
final readonly class AwardCertificateIssuer
{
    public function __construct(
        private CertificateNumberGenerator $numbers,
        private VerificationCode $codes,
        private AwardCertificateSnapshotBuilder $snapshots,
        private CertificateEntityLogo $entityLogo,
        private AuditRecorder $audit,
    ) {}

    public function issue(Assessment $assessment, AssessmentResultSnapshot $initialResult, CertificateKind $kind): Certificate
    {
        $issuedAt = Date::now();
        $isTest = $assessment->loadMissing('payment')->payment?->isTest() === true;

        $certificate = Certificate::query()->create([
            'number' => $this->numbers->next($issuedAt),
            'verification_code' => $this->codes->generate(),
            'kind' => $kind,
            'level' => null,
            'status' => CertificateStatus::Issued,
            'is_test' => $isTest,
            'entity_id' => $assessment->entity_id,
            'assessment_id' => $assessment->id,
            'result_snapshot_id' => $initialResult->id,
            'score_percent' => $initialResult->percent,
            'snapshot' => $this->snapshots->build($assessment, $kind),
            'issued_at' => $issuedAt,
            'issued_by_user_id' => null,
            'entity_logo_file_id' => $this->entityLogo->currentFileIdOf($assessment->entity_id),
            'valid_until' => null,
        ]);

        $this->audit->record(new AuditEntry(AuditEvent::CertificateIssued, $certificate, [], [
            'number' => $certificate->number,
            'kind' => $kind->value,
            'assessment_id' => $certificate->assessment_id,
            'award_cycle_id' => $assessment->award_cycle_id,
            'is_test' => $isTest,
        ]));

        CertificateIssued::dispatch($certificate);

        return $certificate;
    }
}
