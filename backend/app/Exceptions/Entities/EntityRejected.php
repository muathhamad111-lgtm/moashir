<?php

declare(strict_types=1);

namespace App\Exceptions\Entities;

use App\Data\Entities\OpenEntityRequest;
use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class EntityRejected extends ApiException
{
    public static function deleted(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'entity.deleted'));
    }

    /**
     * @param  list<OpenEntityRequest>  $openRequests
     */
    public static function openRequestsConfirmationRequired(array $openRequests): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'entity.open_requests_confirmation_required', [], [], [
            'open_requests' => array_map(static fn (OpenEntityRequest $request): array => $request->toArray(), $openRequests),
            'open_requests_count' => count($openRequests),
        ]));
    }

    public static function notSoftDeleted(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'entity.not_soft_deleted'));
    }

    public static function confirmationMismatch(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'confirmation.mismatch', [
            'confirmation' => [__('errors.confirmation.mismatch')],
        ]));
    }

    public static function disclaimerRequired(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'disclaimer.required', [
            'disclaimer_acknowledged' => [__('errors.disclaimer.required')],
        ]));
    }

    public static function verificationCooldown(int $retryAfterSeconds): self
    {
        return new self(new ApiError(Response::HTTP_TOO_MANY_REQUESTS, 'entity.verification_cooldown', [], [
            'Retry-After' => (string) max(1, $retryAfterSeconds),
        ]));
    }

    public static function exportTooLarge(int $limit): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'export.too_large', [], [], ['limit' => $limit]));
    }
}
