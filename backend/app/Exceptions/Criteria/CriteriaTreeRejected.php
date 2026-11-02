<?php

declare(strict_types=1);

namespace App\Exceptions\Criteria;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class CriteriaTreeRejected extends ApiException
{
    public static function subEvaluatedDirectly(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'criteria.sub_evaluated_directly', [
            'evaluation_type' => [__('errors.criteria.sub_evaluated_directly')],
        ]));
    }

    public static function pointsConfirmationRequired(int $pointCount): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'criteria.points_confirm_required', [
            'confirm_delete_points' => [__('errors.criteria.points_confirm_required_detail', ['count' => (string) $pointCount])],
        ], [], ['impact' => ['point_count' => $pointCount]]));
    }

    public static function orderIncomplete(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'order.incomplete', [
            'ordered_ids' => [__('errors.order.incomplete')],
        ]));
    }

    public static function nodeNotInVersion(int $index): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'criteria.node_not_in_version', [
            "nodes.{$index}.id" => [__('errors.criteria.node_not_in_version')],
        ]));
    }
}
