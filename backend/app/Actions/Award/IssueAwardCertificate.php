<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Enums\Certificates\CertificateKind;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Models\Certificates\Certificate;
use App\Services\Assessments\AssessmentRowLock;
use App\Services\Certificates\AwardCertificateIssuer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Issues one award certificate of one kind for one request, at most once: a second call (a retried job, a backfill
 * after the event already ran) returns the certificate already there. A revoked or replaced one counts too, so
 * automation never undoes a System Administrator's revocation.
 *
 * The request row lock serializes concurrent calls; the partial unique index (assessment, kind) is the last line of
 * defence, and its violation means "issued". A frozen request, or one with no completed initial assessment, gets
 * nothing.
 */
final readonly class IssueAwardCertificate
{
    private const array RELATIONS = ['awardCycle', 'region', 'ranking', 'payment'];

    public function __construct(private AssessmentRowLock $rows, private AwardCertificateIssuer $issuer) {}

    public function handle(Assessment $assessment, CertificateKind $kind): ?Certificate
    {
        return DB::transaction(function () use ($assessment, $kind): ?Certificate {
            $locked = $this->rows->lock($assessment->id);
            $initialResult = $locked->currentResult;

            if (! $locked->isAdvanced() || $locked->isFrozen() || ! $initialResult instanceof AssessmentResultSnapshot) {
                return null;
            }

            return $this->existingOf($locked, $kind) ?? $this->issueOnce($locked->load(self::RELATIONS), $initialResult, $kind);
        });
    }

    private function issueOnce(Assessment $locked, AssessmentResultSnapshot $initialResult, CertificateKind $kind): ?Certificate
    {
        try {
            return DB::transaction(fn (): Certificate => $this->issuer->issue($locked, $initialResult, $kind));
        } catch (UniqueConstraintViolationException) {
            return $this->existingOf($locked, $kind);
        }
    }

    private function existingOf(Assessment $assessment, CertificateKind $kind): ?Certificate
    {
        return Certificate::query()
            ->where('assessment_id', $assessment->id)
            ->where('kind', $kind)
            ->latest('id')
            ->first();
    }
}
