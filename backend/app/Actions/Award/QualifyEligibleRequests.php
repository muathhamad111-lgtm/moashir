<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Award\BulkQualificationOutcome;
use App\Data\Award\FilteringInstruction;
use App\Exceptions\ApiException;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Exceptions\Award\AwardRejected;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use App\Services\Award\QualificationCandidateFinder;

/**
 * «تأهيل كل من بلغ حد التأهل» (operator decision D-4, 2026-11-22): qualifies, one by one through the single
 * qualify action, every request awaiting filtering that reaches the threshold. Each request keeps its own lock,
 * decision row, audit entry and notice, and a request decided meanwhile is skipped. Below-threshold requests
 * stay for an individual decision. Running it twice qualifies nothing new.
 */
final readonly class QualifyEligibleRequests
{
    private const string STATE_CHANGED = 'state.transition_illegal';

    public function __construct(private QualificationCandidateFinder $candidates, private QualifyAdvancedRequest $qualify) {}

    public function handle(User $actor, AwardCycle $cycle, ?string $reason): BulkQualificationOutcome
    {
        if (! $cycle->status->allowsFilteringDecisions()) {
            throw AwardRejected::filteringNotOpen();
        }

        // The Super Admin's reason is checked once here, not refused again for every request.
        if ($actor->isSuperAdmin() && trim((string) $reason) === '') {
            throw AssessmentRejected::reasonRequired();
        }

        $qualified = [];
        $skipped = [];

        foreach ($this->candidates->of($cycle, $actor)->eligible as $assessment) {
            $result = $this->qualifyOne($actor, $cycle, $assessment, $reason);
            $result === null ? $qualified[] = $assessment->id : $skipped[] = ['id' => $assessment->id, 'reason' => $result];
        }

        return new BulkQualificationOutcome($qualified, $skipped);
    }

    /**
     * @return string|null the skip reason, or null when the request was qualified
     */
    private function qualifyOne(User $actor, AwardCycle $cycle, Assessment $assessment, ?string $reason): ?string
    {
        try {
            $this->qualify->handle($actor, $cycle, $assessment, new FilteringInstruction(reason: $reason));

            return null;
        } catch (ApiException $refused) {
            $code = $refused->error()->code;

            return $code === self::STATE_CHANGED ? 'already_decided' : $code;
        }
    }
}
