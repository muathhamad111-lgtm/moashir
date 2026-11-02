<?php

declare(strict_types=1);

namespace App\Exceptions\Criteria;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use App\Support\ValueObjects\Weight;
use Illuminate\Http\Response;

final class CriteriaWeightRejected extends ApiException
{
    public static function exceedsRemaining(Weight $remaining): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'criteria.weight_exceeds_remaining', [
            'weight' => [__('errors.criteria.weight_exceeds_remaining_detail', ['remaining' => $remaining->toDecimal()])],
        ], [], ['remaining_weight' => $remaining->toDecimal()]));
    }

    public static function belowAllocated(Weight $allocated): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'criteria.weight_below_allocated', [
            'weight' => [__('errors.criteria.weight_below_allocated_detail', ['allocated' => $allocated->toDecimal()])],
        ], [], ['allocated_weight' => $allocated->toDecimal()]));
    }

    public static function required(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'criteria.weight_required', [
            'weight' => [__('errors.criteria.weight_required')],
        ]));
    }

    public static function tooSmallToDistribute(Weight $total, int $shares): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'criteria.weight_too_small_to_distribute', [
            'weight' => [__('errors.criteria.weight_too_small_to_distribute_detail', ['weight' => $total->toDecimal(), 'shares' => (string) $shares])],
        ]));
    }
}
