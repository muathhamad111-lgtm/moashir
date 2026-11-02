<?php

declare(strict_types=1);

namespace App\Exceptions\Identity;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class RoleAssignmentRejected extends ApiException
{
    public static function notAssignable(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'role_not_assignable', [
            'roles' => [__('validation.custom.roles.not_assignable')],
        ]));
    }

    public static function forbiddenCombination(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'sod_role_combination'));
    }

    public static function selfGrant(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'sod_self_grant'));
    }
}
