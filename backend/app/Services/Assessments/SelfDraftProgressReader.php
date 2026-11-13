<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\ScorableUnit;
use App\Data\Assessments\ScoringSub;
use App\Data\Paths\DraftProgress;
use App\Models\Assessments\Assessment;

final readonly class SelfDraftProgressReader
{
    public function __construct(private ScoringTreeBuilder $trees, private ScoreSheetReader $scores) {}

    public function read(Assessment $draft): DraftProgress
    {
        $tree = $this->trees->build($draft->criteriaVersion);
        $missing = array_map(static fn (ScorableUnit $unit): string => $unit->key(), $tree->unscoredUnits($this->scores->read($draft, $tree)));
        $total = 0;
        $complete = 0;

        foreach ($tree->mains as $main) {
            foreach ($main->subs as $sub) {
                $total++;
                $complete += $this->isScored($sub, $missing) ? 1 : 0;
            }
        }

        return new DraftProgress($complete, $total);
    }

    /**
     * @param  list<string>  $missing
     */
    private function isScored(ScoringSub $sub, array $missing): bool
    {
        foreach ($sub->scorableUnits() as $unit) {
            if (in_array($unit->key(), $missing, true)) {
                return false;
            }
        }

        return true;
    }
}
