<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\CriteriaRebind;
use App\Data\Assessments\ReboundAssessment;

final readonly class AssessmentRebinder
{
    public function __construct(
        private AssessmentRowLock $rowLock,
        private ScoreSheetReader $reader,
        private CriteriaRebindPlanner $planner,
        private ScoreSheetWriter $writer,
    ) {}

    public function rebind(int $assessmentId, CriteriaRebind $rebind): ?ReboundAssessment
    {
        $assessment = $this->rowLock->lock($assessmentId);

        if (! $assessment->followsCriteriaEdits() || $assessment->criteria_version_id !== $rebind->from->versionId || $assessment->isFrozen()) {
            return null;
        }

        $plan = $this->planner->plan($this->reader->currentRows($assessment), $rebind);

        $assessment->forceFill(['criteria_version_id' => $rebind->to->versionId])->save();
        $assessment->unsetRelation('criteriaVersion');
        $this->writer->applyRebind($assessment, $plan);

        return new ReboundAssessment($assessment, $plan);
    }
}
