<?php

declare(strict_types=1);

namespace App\Enums\Identity;

enum IdentityChangeKind: string
{
    /** The account's first national ID, entered by its holder: no reason, no password, approved like any change. */
    case FirstEntry = 'first_entry';
    case Correction = 'correction';
}
