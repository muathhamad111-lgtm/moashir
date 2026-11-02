<?php

declare(strict_types=1);

namespace App\Exceptions\Entities;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class EmailChangeRejected extends ApiException
{
    public static function pendingApproval(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'email_change.pending_approval'));
    }

    public static function addressTaken(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'email_change.address_taken'));
    }
}
