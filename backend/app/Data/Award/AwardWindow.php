<?php

declare(strict_types=1);

namespace App\Data\Award;

use App\Enums\Award\AwardWindowState;
use App\Models\Award\AwardCycle;

final readonly class AwardWindow
{
    public function __construct(public AwardWindowState $state, public ?AwardCycle $cycle, public ?AwardCycle $nextCycle) {}

    public function feeCycle(): ?AwardCycle
    {
        return match ($this->state) {
            AwardWindowState::Open, AwardWindowState::Upcoming => $this->cycle,
            AwardWindowState::Closed => $this->nextCycle,
            AwardWindowState::None => null,
        };
    }
}
