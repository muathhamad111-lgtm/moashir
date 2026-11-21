<?php

declare(strict_types=1);

namespace App\Enums\Cms;

use App\Enums\Identity\TermsKind;

enum LegalDocument: string
{
    case TermsOfUse = 'terms-of-use';
    case PrivacyPolicy = 'privacy-policy';
    case AccessibilityStatement = 'accessibility-statement';

    public static function of(TermsKind $kind): self
    {
        return match ($kind) {
            TermsKind::Terms => self::TermsOfUse,
            TermsKind::Privacy => self::PrivacyPolicy,
        };
    }
}
