<?php

declare(strict_types=1);

namespace App\Enums\Identity;

enum TermsVersionStatus: string
{
    case Draft = 'draft';
    case Current = 'current';
    case Superseded = 'superseded';
}
