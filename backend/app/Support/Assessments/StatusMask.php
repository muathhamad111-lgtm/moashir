<?php

declare(strict_types=1);

namespace App\Support\Assessments;

use App\Data\Assessments\StatusHistoryEntry;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Identity\PermissionKey;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;

final class StatusMask
{
    private const array RESULT_READER_PERMISSIONS = [
        PermissionKey::RequestsQueueView,
        PermissionKey::AwardFilteringView,
        PermissionKey::AwardSecretaryReview,
    ];

    public static function statusFor(Assessment $assessment, mixed $viewer): string
    {
        return self::valueFor($assessment, $viewer, (string) $assessment->status->value);
    }

    /**
     * The status as this viewer may see it: a masked award stage becomes the placeholder the viewer sees for the
     * current status too, so a status history cannot reveal what the current status hides (AJ-03).
     */
    public static function valueFor(Assessment $assessment, mixed $viewer, string $status): string
    {
        if (! self::hidesFrom($assessment, $viewer, $status)) {
            return $status;
        }

        return self::isOwner($assessment, $viewer) ? AdvancedAssessmentStatus::ENTITY_PLACEHOLDER : AdvancedAssessmentStatus::AwaitingFiltering->value;
    }

    /**
     * A history step into a masked stage is dropped, except the owner's step into it from a visible state (its
     * submission or resubmission), which then reads as the placeholder.
     */
    public static function showsHistoryStep(Assessment $assessment, mixed $viewer, StatusHistoryEntry $entry): bool
    {
        if (! self::hidesFrom($assessment, $viewer, $entry->to)) {
            return true;
        }

        return self::isOwner($assessment, $viewer) && $entry->from !== null && ! self::hidesFrom($assessment, $viewer, $entry->from);
    }

    public static function isOpenFor(Assessment $assessment, mixed $viewer): bool
    {
        return $assessment->isOpen() || self::hidesFrom($assessment, $viewer, (string) $assessment->status->value);
    }

    /**
     * @return list<string>
     */
    public static function hiddenStatusValuesFor(User $viewer): array
    {
        if ($viewer->entity_id !== null) {
            return AdvancedAssessmentStatus::valuesOf(...AdvancedAssessmentStatus::ownerHiddenStates());
        }

        $masked = AdvancedAssessmentStatus::filteringVisibleStates();

        $outcomes = array_values(array_filter($masked, static fn (AdvancedAssessmentStatus $status): bool => $status !== AdvancedAssessmentStatus::AwaitingFiltering));

        return self::readsResults($viewer) ? [] : AdvancedAssessmentStatus::valuesOf(...$outcomes);
    }

    public static function hidesFrom(Assessment $assessment, mixed $viewer, string $status): bool
    {
        $state = AdvancedAssessmentStatus::tryFrom($status);

        if (! $assessment->isAdvanced() || ! $state instanceof AdvancedAssessmentStatus) {
            return false;
        }

        if (self::isOwner($assessment, $viewer)) {
            return $state->isHiddenFromOwnerUntilAnnouncement() && ! self::isAnnounced($assessment);
        }

        return $state->isHiddenFromEntityUntilAnnouncement()
            && $state !== AdvancedAssessmentStatus::AwaitingFiltering
            && ! self::readsResults($viewer)
            && ! self::isAnnounced($assessment);
    }

    private static function isOwner(Assessment $assessment, mixed $viewer): bool
    {
        return $viewer instanceof User && $assessment->isOwnedBy($viewer->entity_id);
    }

    private static function readsResults(mixed $viewer): bool
    {
        return $viewer instanceof User && array_any(self::RESULT_READER_PERMISSIONS, static fn (PermissionKey $permission): bool => $viewer->can($permission->value));
    }

    private static function isAnnounced(Assessment $assessment): bool
    {
        return $assessment->loadMissing('awardCycle')->awardCycle?->isAnnounced() === true;
    }
}
