<?php

declare(strict_types=1);

namespace App\Exceptions\Award;

use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class AwardRejected extends ApiException
{
    public static function anotherCycleRunning(int $cycleId): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'cycle.another_open', [], [], ['award_cycle_id' => $cycleId]));
    }

    public static function datesUnordered(): self
    {
        return self::fieldError('cycle.dates_unordered', 'submission_start');
    }

    /**
     * @param  list<string>  $fields
     */
    public static function fieldsLocked(array $fields): self
    {
        $errors = [];

        foreach ($fields as $field) {
            $errors[$field] = [__('errors.cycle.field_locked')];
        }

        return new self(new ApiError(Response::HTTP_CONFLICT, 'cycle.field_locked', $errors, [], ['locked_fields' => $fields]));
    }

    public static function regionHasRequests(): self
    {
        return self::fieldError('cycle.region_has_requests', 'region_ids');
    }

    public static function criteriaVersionNotPublished(): self
    {
        return self::fieldError('cycle.criteria_version_not_published', 'criteria_version_id');
    }

    public static function participationSettingReserved(): self
    {
        return new self(new ApiError(Response::HTTP_FORBIDDEN, 'cycle.participation_setting_reserved', [
            'participation_certificate_enabled' => [__('errors.cycle.participation_setting_reserved')],
        ]));
    }

    public static function submissionWindowPassed(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'cycle.submission_window_passed'));
    }

    /**
     * @param  list<array{id: int, number: string, status: string}>  $openRequests
     */
    public static function requestsPending(array $openRequests): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'cycle.requests_pending', [], [], [
            'open_requests_count' => count($openRequests),
            'open_requests' => $openRequests,
        ]));
    }

    public static function submissionClosed(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'cycle.submission_closed'));
    }

    /**
     * @param  array<string, list<string>>  $fields
     * @param  list<array<string, mixed>>  $failures
     */
    public static function conditionsFailed(array $fields, array $failures): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'cycle.conditions_failed', $fields, [], ['conditions' => $failures]));
    }

    public static function requestExists(int $assessmentId): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'advanced.request_exists', [], [], ['assessment_id' => $assessmentId]));
    }

    public static function memberInvalid(string $field): self
    {
        return self::fieldError('cycle.member_invalid', $field);
    }

    public static function initialAssessorInvalid(string $field): self
    {
        return self::fieldError('cycle.initial_assessor_invalid', $field);
    }

    public static function initialAssessorNotQualified(string $field): self
    {
        return self::fieldError('assessor.not_qualified', $field);
    }

    public static function segregationConflict(string $field): self
    {
        return self::fieldError('sod_conflict', $field);
    }

    public static function segregationConflictOnAction(): self
    {
        return new self(new ApiError(Response::HTTP_FORBIDDEN, 'sod_conflict'));
    }

    public static function filteringNotOpen(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'cycle.filtering_not_open'));
    }

    public static function overrideReasonRequired(string $score, string $threshold): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'filtering.override_reason_required', [
            'override_reason' => [__('errors.filtering.override_reason_required')],
        ], [], ['score' => $score, 'threshold' => $threshold]));
    }

    public static function deadlineAfterCommitteeStart(string $committeeStart): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'completion.deadline_after_committee_start', [
            'deadline_at' => [__('errors.completion.deadline_after_committee_start', ['date' => $committeeStart])],
        ], [], ['committee_start' => $committeeStart]));
    }

    private static function fieldError(string $code, string $field): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, $code, [$field => [__('errors.'.$code)]]));
    }
}
