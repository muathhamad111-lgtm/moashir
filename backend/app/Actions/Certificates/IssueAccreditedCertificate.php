<?php

declare(strict_types=1);

namespace App\Actions\Certificates;

use App\Data\Certificates\CertificateIssue;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Certificates\CertificateKind;
use App\Enums\Certificates\CertificateStatus;
use App\Exceptions\Certificates\CertificateRejected;
use App\Exceptions\Entities\EntityRejected;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Models\Certificates\Certificate;
use App\Services\Assessments\AssessmentRowLock;
use App\Services\Certificates\CertificateIssuer;
use App\Services\Certificates\CertificateSettingsReader;
use App\Services\Certificates\CertificateSupersession;
use Illuminate\Support\Facades\DB;

final readonly class IssueAccreditedCertificate
{
    public function __construct(
        private CertificateSettingsReader $settings,
        private AssessmentRowLock $rows,
        private CertificateIssuer $issuer,
        private CertificateSupersession $supersession,
    ) {}

    public function handle(Assessment $assessment, ?int $issuerId): Certificate
    {
        if (! $this->settings->accreditedIssuanceEnabled()) {
            throw CertificateRejected::issuanceDisabled();
        }

        return DB::transaction(function () use ($assessment, $issuerId): Certificate {
            $locked = $this->rows->lock($assessment->id);
            $result = $this->completedResultOf($locked);

            return $this->certificateOf($locked, CertificateStatus::Issued) ?? $this->issueReplacingSuspended($locked, $result, $issuerId);
        });
    }

    private function completedResultOf(Assessment $assessment): AssessmentResultSnapshot
    {
        if ($assessment->isFrozen()) {
            throw EntityRejected::deleted();
        }

        $result = $assessment->currentResult;

        if (! $assessment->isAccredited() || $assessment->status !== AccreditedAssessmentStatus::Completed || ! $result instanceof AssessmentResultSnapshot) {
            throw CertificateRejected::assessmentNotCompleted();
        }

        return $result;
    }

    private function issueReplacingSuspended(Assessment $assessment, AssessmentResultSnapshot $result, ?int $issuerId): Certificate
    {
        $suspended = $this->certificateOf($assessment, CertificateStatus::Suspended);

        if (! $suspended instanceof Certificate) {
            return $this->issuer->issue(new CertificateIssue($assessment, $result, $issuerId));
        }

        $this->supersession->supersede($suspended);

        return $this->issuer->issue(new CertificateIssue($assessment, $result, $issuerId, $suspended, (string) $suspended->suspended_reason));
    }

    private function certificateOf(Assessment $assessment, CertificateStatus $status): ?Certificate
    {
        return Certificate::query()
            ->where('assessment_id', $assessment->id)
            ->where('kind', CertificateKind::Accredited)
            ->where('status', $status)
            ->lockForUpdate()
            ->first();
    }
}
