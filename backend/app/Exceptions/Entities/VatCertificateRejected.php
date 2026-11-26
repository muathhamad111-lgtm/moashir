<?php

declare(strict_types=1);

namespace App\Exceptions\Entities;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class VatCertificateRejected extends ApiException
{
    public static function notRegistered(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'entity.vat_not_registered'));
    }

    public static function missing(): self
    {
        return new self(new ApiError(Response::HTTP_NOT_FOUND, 'entity.vat_certificate_missing'));
    }
}
