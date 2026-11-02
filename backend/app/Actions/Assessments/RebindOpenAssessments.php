<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\CriteriaRebind;
use App\Data\Assessments\RebindCause;
use App\Data\Criteria\CriteriaEditEffect;
use App\Enums\Assessments\RebindTrigger;
use App\Models\Assessments\Assessment;
use App\Models\Criteria\CriteriaEditEvent;
use App\Models\Criteria\CriteriaVersion;
use App\Services\Assessments\AssessmentRebindApplier;
use App\Services\Assessments\ScoringTreeBuilder;
use App\Services\Entities\EntityFreezeGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final readonly class RebindOpenAssessments
{
    private const int CHUNK = 100;

    public function __construct(
        private ScoringTreeBuilder $trees,
        private AssessmentRebindApplier $applier,
        private EntityFreezeGuard $freezeGuard,
    ) {}

    /**
     * @param  list<string>  $rescoredCodes
     */
    public function handle(CriteriaEditEvent $edit, array $rescoredCodes): int
    {
        $effect = new CriteriaEditEffect($edit->kind, $edit->scope);

        if (! $effect->rebindsOpenAssessments() || $edit->to_version_id === null) {
            return 0;
        }

        $rebind = new CriteriaRebind(
            $this->trees->build(CriteriaVersion::query()->findOrFail($edit->from_version_id)),
            $this->trees->build(CriteriaVersion::query()->findOrFail($edit->to_version_id)),
            $effect,
            $rescoredCodes,
        );
        $cause = new RebindCause(RebindTrigger::CriteriaEdit, [$edit->id]);
        $rebound = 0;

        $this->openDraftsBoundTo($rebind)->chunkById(self::CHUNK, function (Collection $drafts) use ($rebind, $cause, &$rebound): void {
            foreach ($drafts as $draft) {
                $rebound += $this->applier->apply($draft->id, $rebind, $cause) ? 1 : 0;
            }
        });

        return $rebound;
    }

    /**
     * @return Builder<Assessment>
     */
    private function openDraftsBoundTo(CriteriaRebind $rebind): Builder
    {
        return $this->freezeGuard->excludeFrozen(Assessment::query()
            ->select('id')
            ->followingCriteriaEdits()
            ->where('criteria_version_id', $rebind->from->versionId));
    }
}
