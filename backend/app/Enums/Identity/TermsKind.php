<?php

declare(strict_types=1);

namespace App\Enums\Identity;

enum TermsKind: string
{
    case Terms = 'terms';
    case Privacy = 'privacy';
    case Nda = 'nda';

    /**
     * The public legal texts (terms of use and privacy policy), managed from the content pages. The staff
     * non-disclosure agreement is not public and has its own settings page and permission.
     *
     * @return list<self::Terms|self::Privacy>
     */
    public static function legal(): array
    {
        return [self::Terms, self::Privacy];
    }

    public function isLegal(): bool
    {
        return in_array($this, self::legal(), true);
    }
}
