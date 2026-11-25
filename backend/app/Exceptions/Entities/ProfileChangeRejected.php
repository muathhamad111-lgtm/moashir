<?php

declare(strict_types=1);

namespace App\Exceptions\Entities;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class ProfileChangeRejected extends ApiException
{
    public static function amended(int $revision): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'profile.change_amended', meta: ['revision' => $revision]));
    }

    /**
     * @param  list<string>  $fields
     */
    public static function stale(array $fields): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'profile.change_stale', meta: ['stale_fields' => $fields]));
    }

    public static function fileNotClean(string $item): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'profile.change_file_not_clean', meta: ['item' => $item]));
    }

    public static function unknownItems(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'profile.change_unknown_items', ['approved_items' => [__('errors.profile.change_unknown_items')]]));
    }

    public static function coupledItems(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'profile.change_coupled_items', ['approved_items' => [__('errors.profile.change_coupled_items')]]));
    }

    public static function authorizationLetterRequired(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'profile.change_authorization_letter_required', ['authorization_letter' => [__('errors.profile.change_authorization_letter_required')]]));
    }

    public static function reasonRequired(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'profile.change_reason_required', ['reason' => [__('errors.profile.change_reason_required')]]));
    }

    public static function missing(): self
    {
        return new self(new ApiError(Response::HTTP_NOT_FOUND, 'profile.change_missing'));
    }
}
