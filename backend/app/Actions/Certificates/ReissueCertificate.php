<?php

declare(strict_types=1);

namespace App\Actions\Certificates;

use App\Data\Certificates\CertificateIssue;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Certificates\CertificateStatus;
use App\Exceptions\Certificates\CertificateRejected;
use App\Exceptions\Entities\EntityRejected;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Models\Certificates\Certificate;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentRowLock;
use App\Services\Assessments\StaffActionReason;
use App\Services\Certificates\CertificateIssuer;
use App\Services\Certificates\CertificateSupersession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final readonly class ReissueCertificate
{
    public function __construct(
        private StaffActionReason $reasons,
        private AssessmentRowLock $rows,
        private CertificateIssuer $issuer,
        private CertificateSupersession $supersession,
    ) {}

    public function handle(User $actor, Certificate $certificate, string $reason): Certificate
    {
        $this->reasons->record($actor, $reason);

        return DB::transaction(function () use ($actor, $certificate, $reason): Certificate {
            $assessment = $this->rows->lock($certificate->assessment_id);
            $source = Certificate::query()->whereKey($certificate->id)->lockForUpdate()->firstOrFail();

            if ($source->status === CertificateStatus::Superseded) {
                throw CertificateRejected::noLongerValid($source->status);
            }

            if ($this->hasReplacement($source)) {
                throw CertificateRejected::replacementExists();
            }

            $result = $this->completedResultOf($assessment);
            $this->supersession->supersede($source);

            return $this->issuer->issue(new CertificateIssue($assessment, $result, $actor->id, $source, $reason));
        });
    }

    private function completedResultOf(Assessment $assessment): AssessmentResultSnapshot
    {
        if ($assessment->isFrozen()) {
            throw EntityRejected::deleted();
        }

        $result = $assessment->currentResult;

        if ($assessment->status !== AccreditedAssessmentStatus::Completed || ! $result instanceof AssessmentResultSnapshot) {
            throw CertificateRejected::assessmentNotCompleted();
        }

        return $result;
    }

    private function hasReplacement(Certificate $source): bool
    {
        return Certificate::query()
            ->where('assessment_id', $source->assessment_id)
            ->whereKeyNot($source->id)
            ->where(static fn (Builder $query): Builder => $query->where('status', CertificateStatus::Issued)->orWhere('replaces_certificate_id', $source->id))
            ->exists();
    }
}
