<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\CriteriaRebind;
use App\Data\Assessments\RebindCause;
use App\Data\Assessments\ReboundAssessment;
use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Events\Assessments\OpenAssessmentReset;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class AssessmentRebindApplier
{
    public function __construct(
        private AssessmentRebinder $rebinder,
        private CriteriaMoveRecorder $moves,
        private AuditRecorder $audit,
    ) {}

    public function apply(int $assessmentId, CriteriaRebind $rebind, RebindCause $cause): bool
    {
        return DB::transaction(function () use ($assessmentId, $rebind, $cause): bool {
            $rebound = $this->rebinder->rebind($assessmentId, $rebind);

            if (! $rebound instanceof ReboundAssessment) {
                return false;
            }

            $this->moves->record($rebound, $rebind, $cause);
            $this->audit->record(new AuditEntry(
                AuditEvent::AssessmentRebound,
                $rebound->assessment,
                ['criteria_version_id' => $rebind->from->versionId],
                [
                    'criteria_version_id' => $rebind->to->versionId,
                    'scope' => $rebind->effect->scope->value,
                    'kept_codes' => $rebound->plan->carriedCodes(),
                    'cleared_count' => count($rebound->plan->cleared),
                    ...$cause->toAuditValues(),
                ],
            ));

            if ($rebind->effect->clearsAllOpenScores()) {
                OpenAssessmentReset::dispatch($rebound->assessment);
            }

            return true;
        });
    }
}
