<?php

declare(strict_types=1);

namespace App\Data\Assessments;

/**
 * Whether staff must give a reason when they assign a request or move it to another assessor.
 * The Super Admin always gives one (override rule).
 */
final readonly class AssignmentReasonRules
{
    public function __construct(public bool $assignRequired, public bool $reassignRequired) {}

    /**
     * @return array{assign_required: bool, reassign_required: bool}
     */
    public function toArray(): array
    {
        return ['assign_required' => $this->assignRequired, 'reassign_required' => $this->reassignRequired];
    }
}
