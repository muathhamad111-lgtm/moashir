<?php

declare(strict_types=1);

namespace App\Exceptions\Assessments;

use App\Data\Assessments\ScorableUnit;
use App\Exceptions\ApiException;
use App\Http\Responses\ApiError;
use Illuminate\Http\Response;

final class AssessmentRejected extends ApiException
{
    public static function selfDraftExists(int $draftId): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'self.draft_exists', [], [], ['assessment_id' => $draftId]));
    }

    public static function entityRequired(): self
    {
        return new self(new ApiError(Response::HTTP_FORBIDDEN, 'assessment.entity_required'));
    }

    public static function entityNotActive(): self
    {
        return new self(new ApiError(Response::HTTP_FORBIDDEN, 'assessment.entity_not_active'));
    }

    /**
     * @param  list<ScorableUnit>  $missing
     */
    public static function scoresIncomplete(array $missing): self
    {
        $fields = [];

        foreach ($missing as $unit) {
            $fields[$unit->fieldKey()] = [__('errors.scores.item_missing', ['code' => $unit->code])];
        }

        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'scores.incomplete', $fields, [], [
            'missing' => array_map(static fn (ScorableUnit $unit): array => $unit->toArray(), $missing),
        ]));
    }

    /**
     * @param  array<string, string>  $reasonsByField
     */
    public static function invalidScoreItems(array $reasonsByField): self
    {
        $fields = array_map(static fn (string $reason): array => [__('errors.'.$reason)], $reasonsByField);
        $reasons = array_values(array_unique($reasonsByField));

        return new self(new ApiError(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            count($reasons) === 1 ? $reasons[0] : 'validation_failed',
            $fields,
        ));
    }

    public static function criteriaUpdateInProgress(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'assessment.criteria_update_in_progress'));
    }

    public static function accreditedRequestOpen(int $assessmentId): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'accredited.request_open', [], [], ['assessment_id' => $assessmentId]));
    }

    public static function accreditedPaymentPending(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'accredited.payment_being_processed'));
    }

    public static function subNotInVersion(string $field): self
    {
        return self::fieldError('assessment.sub_not_in_version', $field);
    }

    public static function cvNotAllowed(string $field): self
    {
        return self::fieldError('cv.not_allowed', $field);
    }

    public static function completionItemNotRequested(string $field): self
    {
        return self::fieldError('completion.item_not_requested', $field);
    }

    public static function completionDeadlinePassed(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'completion.deadline_passed'));
    }

    public static function attachmentKeyInvalid(string $field): self
    {
        return self::fieldError('attachment.key_invalid', $field);
    }

    public static function subEvidenceFull(string $field, int $maximum): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'attachment.sub_full', [
            $field => [__('errors.attachment.sub_full', ['max' => $maximum])],
        ], [], ['max_files_per_sub' => $maximum]));
    }

    /**
     * @param  array<string, list<string>>  $fields
     * @param  array<string, mixed>  $meta
     */
    public static function submissionIncomplete(array $fields, array $meta): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'submission.incomplete', $fields, [], $meta));
    }

    public static function assessorInvalid(): self
    {
        return self::fieldError('assessor.invalid', 'assessor_id');
    }

    public static function assessorUnchanged(): self
    {
        return self::fieldError('assessor.same', 'assessor_id');
    }

    public static function assessorNotInCycle(): self
    {
        return self::fieldError('assessor.not_in_cycle', 'assessor_id');
    }

    public static function assessorConflict(): self
    {
        return self::fieldError('sod_conflict', 'assessor_id');
    }

    public static function selfAssignment(): self
    {
        return self::fieldError('sod_self_assignment', 'assessor_id');
    }

    public static function notAssignee(): self
    {
        return new self(new ApiError(Response::HTTP_FORBIDDEN, 'assessment.not_assignee'));
    }

    public static function assignmentRequired(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'assessment.assignment_required'));
    }

    public static function resultRequired(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'assessment.result_required'));
    }

    public static function bundleEmpty(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'evidence.bundle_empty'));
    }

    public static function bundleNotReady(): self
    {
        return new self(new ApiError(Response::HTTP_CONFLICT, 'evidence.bundle_not_ready'));
    }

    public static function reasonRequired(): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, 'reason_required', [
            'reason' => [__('errors.reason_required')],
        ]));
    }

    private static function fieldError(string $code, string $field): self
    {
        return new self(new ApiError(Response::HTTP_UNPROCESSABLE_ENTITY, $code, [$field => [__('errors.'.$code)]]));
    }
}
