<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Data\Award\AwardWindow;
use App\Enums\Award\AwardWindowState;
use App\Models\Award\AwardCycle;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

final readonly class AwardWindowReader
{
    public function __construct(private CurrentCycleReader $cycles) {}

    public function read(): AwardWindow
    {
        $now = Date::now();
        $cycle = $this->cycles->running();
        $state = $this->stateOf($cycle, $now);
        $next = $state === AwardWindowState::Closed && $cycle instanceof AwardCycle ? $this->nextAfter($cycle, $now) : null;

        return new AwardWindow($state, $cycle, $next);
    }

    private function stateOf(?AwardCycle $cycle, CarbonInterface $now): AwardWindowState
    {
        return match (true) {
            ! $cycle instanceof AwardCycle => AwardWindowState::None,
            $cycle->acceptsApplicationsAt($now) => AwardWindowState::Open,
            $now->lessThan($cycle->submission_start) => AwardWindowState::Upcoming,
            default => AwardWindowState::Closed,
        };
    }

    private function nextAfter(AwardCycle $cycle, CarbonInterface $now): ?AwardCycle
    {
        return AwardCycle::query()->running()->whereKeyNot($cycle->id)->where('submission_start', '>', $now)->orderBy('submission_start')->first();
    }
}
