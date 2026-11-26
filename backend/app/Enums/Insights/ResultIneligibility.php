<?php

declare(strict_types=1);

namespace App\Enums\Insights;

enum ResultIneligibility: string
{
    case NotCompleted = 'not_completed';
    case NotAccredited = 'not_accredited';
    case CertificateRevoked = 'certificate_revoked';
    case UnderReview = 'under_review';

    public function isExclusion(): bool
    {
        return $this === self::CertificateRevoked || $this === self::UnderReview;
    }
}
