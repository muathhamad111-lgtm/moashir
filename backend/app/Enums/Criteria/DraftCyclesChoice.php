<?php

declare(strict_types=1);

namespace App\Enums\Criteria;

enum DraftCyclesChoice: string
{
    case Keep = 'keep';
    case Move = 'move';
}
