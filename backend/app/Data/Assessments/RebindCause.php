<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Enums\Assessments\RebindTrigger;

final readonly class RebindCause
{
    /**
     * @param  list<int>  $editEventIds
     */
    public function __construct(public RebindTrigger $trigger, public array $editEventIds) {}

    /**
     * @return array{trigger: string, edit_event_ids: list<int>}
     */
    public function toAuditValues(): array
    {
        return ['trigger' => $this->trigger->value, 'edit_event_ids' => $this->editEventIds];
    }
}
