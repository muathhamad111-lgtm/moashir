<?php

declare(strict_types=1);

namespace App\Enums\Identity;

enum TermsKind: string
{
    case Terms = 'terms';
    case Privacy = 'privacy';
}
