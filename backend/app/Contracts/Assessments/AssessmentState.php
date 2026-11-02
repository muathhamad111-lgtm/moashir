<?php

declare(strict_types=1);

namespace App\Contracts\Assessments;

use BackedEnum;

interface AssessmentState extends BackedEnum
{
    public function isOpen(): bool;

    public function exposesResult(): bool;

    public function allowsTransitionTo(self $next): bool;
}
