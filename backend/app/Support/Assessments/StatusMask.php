<?php

declare(strict_types=1);

namespace App\Support\Assessments;

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
        $status = (string) $assessment->status->value;

        if (! self::hidesFrom($assessment, $viewer, $status)) {
            return $status;
        }

        return self::isOwner($assessment, $viewer) ? AdvancedAssessmentStatus::ENTITY_PLACEHOLDER : AdvancedAssessmentStatus::AwaitingFiltering->value;
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
        $masked = AdvancedAssessmentStatus::filteringVisibleStates();

        if ($viewer->entity_id !== null) {
            return AdvancedAssessmentStatus::valuesOf(...$masked);
        }

        $outcomes = array_values(array_filter($masked, static fn (AdvancedAssessmentStatus $status): bool => $status !== AdvancedAssessmentStatus::AwaitingFiltering));

        return self::readsResults($viewer) ? [] : AdvancedAssessmentStatus::valuesOf(...$outcomes);
    }

    public static function hidesFrom(Assessment $assessment, mixed $viewer, string $status): bool
    {
        $state = AdvancedAssessmentStatus::tryFrom($status);

        if (! $assessment->isAdvanced() || ! $state instanceof AdvancedAssessmentStatus || ! $state->isHiddenFromEntityUntilAnnouncement()) {
            return false;
        }

        if (self::isAnnounced($assessment)) {
            return false;
        }

        if (self::isOwner($assessment, $viewer)) {
            return true;
        }

        return $state !== AdvancedAssessmentStatus::AwaitingFiltering && ! self::readsResults($viewer);
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
