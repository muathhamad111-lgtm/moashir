<?php

declare(strict_types=1);

namespace App\Exceptions\Identity;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class PermissionMatrixRejected extends ApiException
{
    /**
     * @param  list<string>  $keys
     */
    public static function unknownKeys(array $keys): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'permission.unknown', ['permission_keys' => $keys]));
    }

    /**
     * @param  list<string>  $keys
     */
    public static function lockedKeys(array $keys): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'permission.locked', ['permission_keys' => $keys]));
    }

    public static function roleNotEditable(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'role.not_editable'));
    }

    public static function selfGrant(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'sod_self_grant'));
    }
}
