<?php

declare(strict_types=1);

namespace App\Enums\Assessments;

enum ScorerRole: string
{
    case Entity = 'entity';
    case Assessor = 'assessor';
}
