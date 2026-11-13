<?php

declare(strict_types=1);

namespace App\Exceptions\Certificates;

use App\Enums\Certificates\CertificateStatus;
use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class CertificateRejected extends ApiException
{
    public static function issuanceDisabled(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'certificate.issuance_disabled'));
    }

    public static function assessmentNotCompleted(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'certificate.assessment_not_completed'));
    }

    public static function notIssued(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'certificate.not_issued'));
    }

    public static function replacementExists(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'certificate.replacement_exists'));
    }

    public static function noLongerValid(CertificateStatus $status): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, match ($status) {
            CertificateStatus::Revoked => 'certificate.revoked',
            CertificateStatus::Superseded => 'certificate.superseded',
            CertificateStatus::Suspended => 'certificate.suspended',
            CertificateStatus::Issued => 'certificate.not_issued',
        }));
    }
}
