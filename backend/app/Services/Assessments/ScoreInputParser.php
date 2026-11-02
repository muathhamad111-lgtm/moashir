<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\ScorableUnit;
use App\Data\Assessments\ScoredUnit;
use App\Data\Assessments\ScoreSheet;
use App\Data\Assessments\ScoringTree;
use App\Data\Assessments\SubmittedScores;
use App\Enums\Assessments\ScoreLevel;
use App\Enums\Criteria\ScaleKind;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Support\ValueObjects\RawScore;
use InvalidArgumentException;

final class ScoreInputParser
{
    private const string NOT_IN_VERSION = 'score.item_not_in_version';

    private const string USE_POINTS = 'score.use_points';

    private const string OUT_OF_RANGE = 'score.out_of_range';

    public function parse(SubmittedScores $input, ScoringTree $tree, ScaleKind $scale): ScoreSheet
    {
        $entries = [];
        $errors = [];

        foreach ([[ScoreLevel::Sub, 'subs', $input->subs], [ScoreLevel::Point, 'points', $input->points]] as [$level, $group, $values]) {
            foreach ($values as $key => $value) {
                $field = 'scores.'.$group.'.'.$key;
                $unit = is_numeric($key) ? $tree->unit($level, (int) $key) : null;

                if (! $unit instanceof ScorableUnit) {
                    $errors[$field] = $this->unknownItemReason($level, $key, $tree);

                    continue;
                }

                if ($value === null) {
                    continue;
                }

                $raw = $this->rawScore($scale, $value);
                $raw instanceof RawScore ? $entries[] = new ScoredUnit($unit, $raw) : $errors[$field] = self::OUT_OF_RANGE;
            }
        }

        if ($errors !== []) {
            throw AssessmentRejected::invalidScoreItems($errors);
        }

        return ScoreSheet::of($entries);
    }

    /**
     * @return list<string>
     */
    public function clearedKeys(SubmittedScores $input, ScoringTree $tree): array
    {
        $keys = [];

        foreach ([[ScoreLevel::Sub, $input->subs], [ScoreLevel::Point, $input->points]] as [$level, $values]) {
            foreach ($values as $key => $value) {
                $unit = is_numeric($key) ? $tree->unit($level, (int) $key) : null;

                if ($value === null && $unit instanceof ScorableUnit) {
                    $keys[] = $unit->key();
                }
            }
        }

        return $keys;
    }

    private function unknownItemReason(ScoreLevel $level, int|string $key, ScoringTree $tree): string
    {
        return $level === ScoreLevel::Sub && is_numeric($key) && $tree->scoresSubThroughPoints((int) $key)
            ? self::USE_POINTS
            : self::NOT_IN_VERSION;
    }

    private function rawScore(ScaleKind $scale, mixed $value): ?RawScore
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        try {
            return RawScore::on($scale, $value);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
