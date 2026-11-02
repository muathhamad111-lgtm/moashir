<?php

declare(strict_types=1);

namespace App\Exceptions\Award;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class ResultsRejected extends ApiException
{
    public static function rankTaken(int $holderId): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'rank.taken', [
            'rank' => [__('errors.rank.taken')],
        ], [], ['assessment_id' => $holderId]));
    }

    public static function regionLocked(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'award.region_locked'));
    }

    public static function regionAlreadyApproved(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'award.region_approved'));
    }

    public static function regionNotApproved(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'award.region_not_approved'));
    }

    public static function nationalLocked(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'award.national_approved'));
    }

    /**
     * @param  list<array{id: int, number: string, status: string}>  $pending
     */
    public static function requestsPending(array $pending): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'region.requests_pending', [], [], [
            'pending_count' => count($pending),
            'pending' => $pending,
        ]));
    }

    /**
     * @param  list<int>  $regionIds
     */
    public static function regionsPending(array $regionIds): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'regions.pending', [], [], ['regions_pending' => $regionIds]));
    }

    public static function noWinner(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'winners.none'));
    }
}
