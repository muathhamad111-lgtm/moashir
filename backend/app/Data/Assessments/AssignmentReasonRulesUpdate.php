<?php

declare(strict_types=1);

namespace App\Data\Assessments;

final readonly class AssignmentReasonRulesUpdate
{
    public function __construct(public AssignmentReasonRules $rules, public ?string $reason) {}
}
