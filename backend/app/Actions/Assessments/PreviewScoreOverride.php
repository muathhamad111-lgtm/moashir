<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\OverridePreview;
use App\Data\Assessments\PreviewedResult;
use App\Data\Assessments\PreviewedScoreChange;
use App\Data\Assessments\ScoreSheet;
use App\Data\Assessments\ScoringTree;
use App\Data\Assessments\SubmittedScores;
use App\Enums\Assessments\AssessmentVerb;
use App\Exceptions\IllegalStateTransition;
use App\Models\Assessments\Assessment;
use App\Services\Assessments\DesiredScoreSheet;
use App\Services\Assessments\ScoreSheetReader;
use App\Services\Assessments\ScoringEngine;
use App\Services\Assessments\ScoringTreeBuilder;
use App\Services\Certificates\CertificateSettingsReader;
use App\Support\ValueObjects\LevelThresholds;

/**
 * What a Super Admin override would do to a published result, computed from plain reads: nothing is locked,
 * written or audited, so the override screen can call it on every edit.
 */
final readonly class PreviewScoreOverride
{
    public function __construct(
        private ScoringTreeBuilder $trees,
        private ScoreSheetReader $reader,
        private DesiredScoreSheet $desiredSheets,
        private ScoringEngine $engine,
        private CertificateSettingsReader $settings,
    ) {}

    public function handle(Assessment $assessment, SubmittedScores $changes): OverridePreview
    {
        if (! $assessment->exposesResult()) {
            throw IllegalStateTransition::from((string) $assessment->status->value, AssessmentVerb::OverrideScore->value);
        }

        $tree = $this->trees->build($assessment->criteriaVersion);
        $current = $this->reader->read($assessment, $tree);
        $desired = $this->desiredSheets->build($assessment, $tree, $changes)->sheet;
        $thresholds = $this->settings->thresholds();

        return new OverridePreview(
            $this->resultOf($tree, $current, $thresholds),
            $this->resultOf($tree, $desired, $thresholds),
            $this->changesBetween($tree, $current, $desired),
        );
    }

    private function resultOf(ScoringTree $tree, ScoreSheet $sheet, LevelThresholds $thresholds): PreviewedResult
    {
        $percent = $this->engine->compute($tree, $sheet)->percent;

        return new PreviewedResult($percent, $thresholds->levelFor($percent));
    }

    /**
     * @return list<PreviewedScoreChange>
     */
    private function changesBetween(ScoringTree $tree, ScoreSheet $current, ScoreSheet $desired): array
    {
        $changes = [];

        foreach ($tree->scorableUnits() as $unit) {
            $change = PreviewedScoreChange::between($unit, $current->rawFor($unit), $desired->rawFor($unit));

            if ($change instanceof PreviewedScoreChange) {
                $changes[] = $change;
            }
        }

        return $changes;
    }
}
