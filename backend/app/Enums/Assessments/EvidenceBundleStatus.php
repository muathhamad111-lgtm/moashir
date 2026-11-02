<?php

declare(strict_types=1);

namespace App\Enums\Assessments;

enum EvidenceBundleStatus: string
{
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Failed = 'failed';
    case Expired = 'expired';
}
