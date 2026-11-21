<?php

declare(strict_types=1);

namespace App\Exceptions\Identity;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class PathAccessRejected extends ApiException
{
    public static function notAssessor(): self
    {
        return self::fieldError('assessor_qualification.not_assessor', 'qualifications');
    }

    public static function notFollower(): self
    {
        return self::fieldError('request_scope.not_follower', 'paths');
    }

    private static function fieldError(string $code, string $field): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, $code, [$field => [__('errors.'.$code)]]));
    }
}
