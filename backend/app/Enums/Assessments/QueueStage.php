<?php

declare(strict_types=1);

namespace App\Enums\Assessments;

use App\Contracts\Assessments\ReviewedAssessmentState;

/**
 * A quick filter of the request queue. The queue lists every request of a path, from the entity's draft
 * to the closed request; a stage narrows it to one part of that life.
 */
enum QueueStage: string
{
    case All = 'all';
    case Preparing = 'preparing';
    case AwaitingAssignment = 'awaiting_assignment';
    case InProgress = 'in_progress';
    case Overdue = 'overdue';
    case Closed = 'closed';

    /**
     * @return list<string>
     */
    public function stateValuesOf(AssessmentPath $path): array
    {
        $awaiting = $path->reviewStages()->awaitingAssignment;

        return match ($this) {
            self::All => [...$path->preparingStateValues(), ...$path->queueStateValues()],
            self::Preparing => $path->preparingStateValues(),
            self::AwaitingAssignment => [(string) $awaiting->value],
            self::InProgress => $path->queueStateValuesWhere(static fn (ReviewedAssessmentState $state): bool => $state->isOpen() && $state !== $awaiting),
            self::Overdue => $path->reviewClockStateValues(),
            self::Closed => $path->queueStateValuesWhere(static fn (ReviewedAssessmentState $state): bool => ! $state->isOpen()),
        };
    }

    /**
     * The stages counted from the requests' states; «all» and «overdue» are counted on their own.
     *
     * @return list<self>
     */
    public static function countedByState(): array
    {
        return [self::Preparing, self::AwaitingAssignment, self::InProgress, self::Closed];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
