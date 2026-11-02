<?php

declare(strict_types=1);

namespace App\Exceptions\Award;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class CommitteeRejected extends ApiException
{
    public static function candidateInvalid(string $field): self
    {
        return self::fieldError('committee.candidate_invalid', $field);
    }

    public static function alreadySeated(string $field): self
    {
        return self::fieldError('committee.member_exists', $field);
    }

    public static function hasScores(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'committee_assessor.has_scores'));
    }

    public static function noneActive(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'committee.none_active'));
    }

    public static function notStarted(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'cycle.committee_not_started'));
    }

    public static function evaluationLocked(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'committee.evaluation_locked'));
    }

    public static function membershipInactive(): self
    {
        return new self(new ApiError(Response::HTTP_FORBIDDEN, 'committee.membership_inactive'));
    }

    /**
     * @param  list<int>  $pendingAssignmentIds
     */
    public static function incomplete(array $pendingAssignmentIds): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'committee.incomplete', [], [], [
            'pending_assignment_ids' => $pendingAssignmentIds,
        ]));
    }

    /**
     * @param  list<int>  $substitutionIds
     */
    public static function substitutionPending(array $substitutionIds): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'committee.substitution_pending', [], [], ['substitution_ids' => $substitutionIds]));
    }

    public static function substituteNotCompleted(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'committee.substitute_not_completed'));
    }

    public static function discussionClosed(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'discussion.closed'));
    }

    public static function discussionDeadlinePassed(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'discussion.deadline_passed'));
    }

    public static function entryKindInvalid(): self
    {
        return self::fieldError('discussion.entry_kind_invalid', 'kind');
    }

    private static function fieldError(string $code, string $field): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, $code, [$field => [__('errors.'.$code)]]));
    }
}
