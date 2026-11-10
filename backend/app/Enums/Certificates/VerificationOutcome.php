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
    case Unverifiable = 'unverifiable';

    public static function ofCertificate(Certificate $certificate, CarbonImmutable $today): self
    {
        return match (true) {
            $certificate->is_test => self::Unverifiable,
            $certificate->isExpiredOn($today) => self::Expired,
            default => self::of($certificate->status),
        };
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

    public function disclosesCertificate(): bool
    {
        return ! in_array($this, [self::NotFound, self::Unverifiable], true);
    }
}
