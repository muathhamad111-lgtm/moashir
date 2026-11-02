<?php

declare(strict_types=1);

namespace App\Exceptions\Criteria;

use App\Data\Criteria\ConsistencyProblem;
use App\Data\Criteria\ConsistencyReport;
use App\Enums\Criteria\CriteriaVersionStatus;
use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class CriteriaVersionRejected extends ApiException
{
    public static function noDraft(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'criteria.no_draft'));
    }

    public static function draftExists(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'criteria.draft_exists'));
    }

    public static function notDraft(CriteriaVersionStatus $status): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'criteria.version_not_draft', ['version_status' => [$status->value]]));
    }

    public static function isDraft(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'criteria.version_is_draft', ['version_status' => [CriteriaVersionStatus::Draft->value]]));
    }

    public static function noPublishedVersion(): self
    {
        return new self(new ApiError(Response::HTTP_NOT_FOUND, 'criteria.no_published_version'));
    }

    public static function cloneSourceInvalid(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'criteria.clone_source_invalid', [
            'clone_from_version_id' => [__('errors.criteria.clone_source_invalid')],
        ]));
    }

    public static function inconsistent(ConsistencyReport $report): self
    {
        $fields = [];

        foreach ($report->problems as $problem) {
            $fields[$problem->fieldKey()][] = $problem->message();
        }

        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'criteria.weights_inconsistent', $fields, [], [
            'problems' => array_map(static fn (ConsistencyProblem $problem): array => $problem->toArray(), $report->problems),
        ]));
    }

    public static function scopeRequired(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'criteria.scope_required', [
            'scope' => [__('errors.criteria.scope_required')],
        ]));
    }

    public static function reasonRequired(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'reason_required', [
            'reason' => [__('errors.reason_required')],
        ]));
    }
}
