<?php

declare(strict_types=1);

namespace App\Enums\Certificates;

use App\Models\Certificates\Certificate;
use Carbon\CarbonImmutable;

enum VerificationOutcome: string
{
    case Valid = 'valid';
    case UnderReview = 'under_review';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case Superseded = 'superseded';
    case NotFound = 'not_found';

    public static function ofCertificate(Certificate $certificate, CarbonImmutable $today): self
    {
        return $certificate->isExpiredOn($today) ? self::Expired : self::of($certificate->status);
    }

    public static function of(CertificateStatus $status): self
    {
        return match ($status) {
            CertificateStatus::Issued => self::Valid,
            CertificateStatus::Suspended => self::UnderReview,
            CertificateStatus::Revoked => self::Revoked,
            CertificateStatus::Superseded => self::Superseded,
        };
    }
}
