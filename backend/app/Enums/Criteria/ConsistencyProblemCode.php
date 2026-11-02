<?php

declare(strict_types=1);

namespace App\Enums\Criteria;

enum ConsistencyProblemCode: string
{
    case NoActiveMain = 'no_active_main';
    case WeightsMismatch = 'weights_mismatch';
    case NoActivePoint = 'no_active_point';
    case PointsExceedSub = 'points_exceed_sub';
}
