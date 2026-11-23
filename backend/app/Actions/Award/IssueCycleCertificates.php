<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Certificates\CertificateKind;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Certificates\Certificate;
use App\Services\Award\AwardCertificateKinds;
use App\Services\Entities\EntityFreezeGuard;
use Illuminate\Database\Eloquent\Collection;

/**
 * At the announcement, every request of the cycle gets the certificates it earned: the winner or qualified one, and
 * the participation one if it is still missing. Each issue is idempotent, so a re-run adds nothing. Frozen requests
 * are skipped; they get theirs when the same run is repeated after a restore.
 */
final readonly class IssueCycleCertificates
{
    private const int CHUNK = 200;

    public function __construct(
        private AwardCertificateKinds $kinds,
        private IssueAwardCertificate $issue,
        private EntityFreezeGuard $freezeGuard,
    ) {}

    public function handle(AwardCycle $cycle): int
    {
        $issued = 0;

        $this->freezeGuard->excludeFrozen(Assessment::query()->inCycle($cycle->id))
            ->chunkById(self::CHUNK, function (Collection $requests) use ($cycle, &$issued): void {
                foreach ($requests as $request) {
                    $issued += $this->issueFor($cycle, $request);
                }
            });

        return $issued;
    }

    private function issueFor(AwardCycle $cycle, Assessment $request): int
    {
        if (! $request->status instanceof AdvancedAssessmentStatus) {
            return 0;
        }

        $kinds = $this->kinds->for($request->status, $request->current_result_snapshot_id !== null, $cycle->participation_certificate_enabled);
        $certificates = array_map(fn (CertificateKind $kind): ?Certificate => $this->issue->handle($request, $kind), $kinds);

        return count(array_filter($certificates));
    }
}
