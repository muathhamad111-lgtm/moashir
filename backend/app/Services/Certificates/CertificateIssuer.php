<?php

declare(strict_types=1);

namespace App\Services\Certificates;

use App\Data\Audit\AuditEntry;
use App\Data\Certificates\CertificateIssue;
use App\Enums\Audit\AuditEvent;
use App\Enums\Certificates\CertificateKind;
use App\Enums\Certificates\CertificateStatus;
use App\Events\Certificates\CertificateIssued;
use App\Models\Certificates\Certificate;
use App\Models\Entities\Entity;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\Date;

final readonly class CertificateIssuer
{
    public function __construct(
        private CertificateNumberGenerator $numbers,
        private VerificationCode $codes,
        private CertificateSettingsReader $settings,
        private CertificateSnapshotBuilder $snapshots,
        private AuditRecorder $audit,
    ) {}

    public function issue(CertificateIssue $issue): Certificate
    {
        $issuedAt = Date::now();
        $isTest = $issue->assessment->loadMissing('payment')->payment?->isTest() === true;
        $thresholds = $this->settings->thresholds();
        $level = $thresholds->levelFor($issue->result->percent);

        $certificate = Certificate::query()->create([
            'number' => $this->numbers->next($issuedAt, $isTest),
            'verification_code' => $this->codes->generate(),
            'kind' => CertificateKind::Accredited,
            'level' => $level,
            'status' => CertificateStatus::Issued,
            'is_test' => $isTest,
            'entity_id' => $issue->assessment->entity_id,
            'assessment_id' => $issue->assessment->id,
            'result_snapshot_id' => $issue->result->id,
            'score_percent' => $issue->result->percent,
            'snapshot' => $this->snapshots->build($issue->assessment, $issue->result, $thresholds, $level),
            'issued_at' => $issuedAt,
            'issued_by_user_id' => $issue->issuedByUserId,
            'replaces_certificate_id' => $issue->replaces?->id,
            'reissue_reason' => $issue->reissueReason,
            'entity_logo_file_id' => $this->currentLogoOf($issue->assessment->entity_id),
        ]);

        $this->audit->record(new AuditEntry(
            $issue->replaces instanceof Certificate ? AuditEvent::CertificateReissued : AuditEvent::CertificateIssued,
            $certificate,
            [],
            [
                'number' => $certificate->number,
                'assessment_id' => $certificate->assessment_id,
                'result_snapshot_id' => $certificate->result_snapshot_id,
                'level' => $level?->value,
                'percent' => $issue->result->percent->toDisplay(),
                'is_test' => $isTest,
                'replaces_certificate_id' => $certificate->replaces_certificate_id,
            ],
        ));

        CertificateIssued::dispatch($certificate);

        return $certificate;
    }

    private function currentLogoOf(int $entityId): ?int
    {
        $logoFileId = Entity::withTrashed()->whereKey($entityId)->sharedLock()->value('logo_file_id');

        return is_int($logoFileId) ? $logoFileId : null;
    }
}
