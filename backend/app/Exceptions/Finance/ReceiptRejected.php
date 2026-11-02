<?php

declare(strict_types=1);

namespace App\Exceptions\Finance;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class ReceiptRejected extends ApiException
{
    public static function voided(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'receipt.voided'));
    }

    public static function notIssued(): self
    {
        return new self(new ApiError(Response::HTTP_NOT_FOUND, 'receipt.not_issued'));
    }
}
