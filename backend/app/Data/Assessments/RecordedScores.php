<?php

declare(strict_types=1);

namespace App\Data\Assessments;

final readonly class RecordedScores
{
    public function __construct(public ScoreSheet $sheet, public ScoreChangeSet $changes) {}
}
