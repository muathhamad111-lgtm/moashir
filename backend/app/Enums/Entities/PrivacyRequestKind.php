<?php

declare(strict_types=1);

namespace App\Enums\Entities;

enum PrivacyRequestKind: string
{
    case Access = 'access';
    case Correction = 'correction';
    case Deletion = 'deletion';
}
