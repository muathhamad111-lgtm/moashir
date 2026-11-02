<?php

declare(strict_types=1);

namespace App\Events\Criteria;

use App\Data\Criteria\CriteriaChange;
use App\Data\Criteria\CriteriaEditEffect;
use App\Models\Criteria\CriteriaEditEvent;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class CriteriaEditApplied implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public CriteriaEditEffect $effect;

    /**
     * @param  list<CriteriaChange>  $changes
     */
    public function __construct(
        public CriteriaEditEvent $editEvent,
        public array $changes,
        public ?int $actorId,
    ) {
        $this->effect = new CriteriaEditEffect($editEvent->kind, $editEvent->scope);
    }

    /**
     * @return list<string>
     */
    public function rescoredCodes(): array
    {
        $codes = [];

        foreach ($this->changes as $change) {
            if ($change->isScoreAffecting()) {
                $codes[] = $change->code;
            }
        }

        return $codes;
    }
}
