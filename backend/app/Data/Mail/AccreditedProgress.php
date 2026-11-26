<?php

declare(strict_types=1);

namespace App\Data\Mail;

use App\Enums\Notifications\AccreditedJourneyStage;
use App\Enums\Notifications\JourneyStepState;

/**
 * Where an accredited request stands, shown as steps: the stages before the current one are done, the rest follow.
 */
final readonly class AccreditedProgress
{
    public function __construct(public AccreditedJourneyStage $current) {}

    /**
     * @return list<AccreditedJourneyStage>
     */
    public function stages(): array
    {
        return AccreditedJourneyStage::cases();
    }

    public function stateOf(AccreditedJourneyStage $stage): JourneyStepState
    {
        return match ($stage->position() <=> $this->current->position()) {
            -1 => JourneyStepState::Done,
            0 => JourneyStepState::Current,
            default => JourneyStepState::Next,
        };
    }
}
