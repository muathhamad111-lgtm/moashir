<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\ReviewNoteChanges;
use App\Data\Assessments\ReviewNoteUnit;
use App\Data\Assessments\ScoringMain;
use App\Data\Assessments\ScoringSub;
use App\Data\Assessments\ScoringTree;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Models\Criteria\CriteriaVersion;

final readonly class ReviewNoteUnitGuard
{
    public function __construct(private ScoringTreeBuilder $trees) {}

    public function assertWithin(CriteriaVersion $version, ReviewNoteChanges $changes): void
    {
        $tree = $this->trees->build($version);

        foreach ($changes->entries as $index => $change) {
            $this->assertUnit($tree, $change->unit, 'notes.'.$index);
        }
    }

    private function assertUnit(ScoringTree $tree, ReviewNoteUnit $unit, string $field): void
    {
        $main = $tree->main($unit->mainCriterionId);

        if (! $main instanceof ScoringMain) {
            throw AssessmentRejected::mainNotInVersion($field.'.main_criterion_id');
        }

        $sub = $unit->subCriterionId === null ? null : $main->sub($unit->subCriterionId);

        if ($unit->level->hasSub() && ! $sub instanceof ScoringSub) {
            throw AssessmentRejected::subNotInVersion($field.'.sub_criterion_id');
        }

        if ($unit->level->hasPoint() && ! ($sub?->scoresPoint((int) $unit->guidingPointId) ?? false)) {
            throw AssessmentRejected::pointNotInVersion($field.'.guiding_point_id');
        }
    }
}
