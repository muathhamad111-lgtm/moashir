<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\DesiredScores;
use App\Data\Assessments\ScoringTree;
use App\Data\Assessments\SubmittedScores;
use App\Models\Assessments\Assessment;

final readonly class DesiredScoreSheet
{
    public function __construct(private ScoreInputParser $parser, private ScoreSheetReader $reader) {}

    public function build(Assessment $assessment, ScoringTree $tree, SubmittedScores $changes): DesiredScores
    {
        $given = $this->parser->parse($changes, $tree, $assessment->scale_kind);

        return new DesiredScores(
            $this->reader->read($assessment, $tree)->merge($given, $this->parser->clearedKeys($changes, $tree)),
            $given,
        );
    }
}
