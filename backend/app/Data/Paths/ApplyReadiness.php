<?php

declare(strict_types=1);

namespace App\Data\Paths;

use App\Enums\Periods\ApplyBlock;
use App\Enums\Periods\SubmissionBlock;

final readonly class ApplyReadiness
{
    public function __construct(public ?ApplyBlock $block = null, public ?SubmissionBlock $warning = null) {}

    public function canStart(): bool
    {
        return $this->block === null;
    }

    /**
     * @return array{can_start: bool, reason: string|null, warning: string|null}
     */
    public function toArray(): array
    {
        return ['can_start' => $this->canStart(), 'reason' => $this->block?->value, 'warning' => $this->warning?->value];
    }
}
