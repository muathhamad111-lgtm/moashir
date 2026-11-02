<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\CriteriaRebind;
use App\Data\Assessments\MissedCriteriaEdits;
use App\Data\Assessments\RebindCause;
use App\Enums\Assessments\RebindTrigger;
use App\Models\Assessments\Assessment;
use App\Models\Criteria\CriteriaVersion;
use App\Services\Assessments\AssessmentRebindApplier;
use App\Services\Assessments\AssessmentRowLock;
use App\Services\Assessments\CriteriaUpdateHold;
use App\Services\Assessments\MissedCriteriaEditsReader;
use App\Services\Assessments\ScoringTreeBuilder;
use Illuminate\Support\Facades\DB;

final readonly class RebindRestoredAssessments
{
    public function __construct(
        private CriteriaUpdateHold $hold,
        private MissedCriteriaEditsReader $missedEdits,
        private AssessmentRowLock $rowLock,
        private ScoringTreeBuilder $trees,
        private AssessmentRebindApplier $applier,
    ) {}

    public function handle(int $entityId): int
    {
        $rebound = 0;

        foreach ($this->hold->draftIdsToSettle($entityId) as $draftId) {
            $rebound += $this->settle($draftId) ? 1 : 0;
        }

        return $rebound;
    }

    private function settle(int $draftId): bool
    {
        return DB::transaction(function () use ($draftId): bool {
            $this->holdCurrentPublication();
            $draft = $this->rowLock->lock($draftId);
            $rebound = $this->moveToPublishedVersion($draft);
            $this->hold->release($draft->id);

            return $rebound;
        });
    }

    private function moveToPublishedVersion(Assessment $draft): bool
    {
        $missed = $this->missedEdits->missedBy($draft->criteria_version_id);
        $targetVersionId = $missed->targetVersionId();

        if (! $missed->rebindsOpenAssessments() || $targetVersionId === null) {
            return false;
        }

        return $this->applier->apply($draft->id, $this->rebindFor($draft, $targetVersionId, $missed), new RebindCause(
            RebindTrigger::EntityRestore,
            $missed->editEventIds(),
        ));
    }

    private function holdCurrentPublication(): void
    {
        CriteriaVersion::query()->published()->sharedLock()->value('id');
    }

    private function rebindFor(Assessment $draft, int $targetVersionId, MissedCriteriaEdits $missed): CriteriaRebind
    {
        return new CriteriaRebind(
            $this->trees->build($draft->criteriaVersion),
            $this->trees->build(CriteriaVersion::query()->findOrFail($targetVersionId)),
            $missed->effect(),
            $missed->rescoredCodes(),
        );
    }
}
