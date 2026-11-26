<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Enums\Assessments\ReviewNoteLevel;
use InvalidArgumentException;

final readonly class ReviewNoteUnit
{
    public function __construct(
        public ReviewNoteLevel $level,
        public int $mainCriterionId,
        public ?int $subCriterionId = null,
        public ?int $guidingPointId = null,
    ) {
        if ($level->hasSub() !== ($subCriterionId !== null) || $level->hasPoint() !== ($guidingPointId !== null)) {
            throw new InvalidArgumentException('The criterion ids of a review note unit must match its level.');
        }
    }

    public function key(): string
    {
        return implode(':', [$this->level->value, $this->mainCriterionId, $this->subCriterionId ?? 0, $this->guidingPointId ?? 0]);
    }
}
