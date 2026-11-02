<?php

declare(strict_types=1);

namespace App\Enums\Assessments;

enum ReviewMode: string
{
    case Edit = 'edit';
    case ReadOnly = 'read_only';
}
