<?php

declare(strict_types=1);

namespace App\Enums\Criteria;

enum CriteriaChangeType: string
{
    case Added = 'added';
    case Removed = 'removed';
    case Modified = 'modified';
    case TextCorrected = 'text_corrected';
}
