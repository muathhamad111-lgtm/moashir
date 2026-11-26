<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Enums\Certificates\CertificateKind;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Certificates\Certificate;
use App\Services\Award\AwardCertificateKinds;

/**
 * The participation certificate of one request: issued as soon as its initial assessment is complete, when its cycle
 * gives participation certificates (operator, 2026-11-22). It reveals no result, so it does not wait for the
 * announcement. Used both on the initial assessment's completion and by the backfill when the setting is turned on.
 */
final readonly class IssueParticipationCertificate
{
    public function __construct(private AwardCertificateKinds $kinds, private IssueAwardCertificate $issue) {}

    public function handle(Assessment $assessment): ?Certificate
    {
        $cycle = $assessment->loadMissing('awardCycle')->awardCycle;
        $applies = $cycle instanceof AwardCycle
            && $this->kinds->participationApplies($assessment->current_result_snapshot_id !== null, $cycle->participation_certificate_enabled);

        return $applies ? $this->issue->handle($assessment, CertificateKind::AdvancedParticipation) : null;
    }
}
