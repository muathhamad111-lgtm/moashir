<?php

declare(strict_types=1);

namespace App\Data\Award;

final readonly class PriorEvaluation
{
    public function __construct(public CommitteeScores $scores, public CommitteeNotes $notes) {}
}
