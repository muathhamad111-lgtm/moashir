<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Identity\ActorKind;
use App\Models\Assessments\AssessmentStatusTransition;
use Carbon\CarbonInterface;

final readonly class StatusHistoryEntry
{
    public function __construct(
        public ?string $from,
        public string $to,
        public AssessmentVerb $verb,
        public CarbonInterface $at,
        public ActorKind $actor,
        public ?int $staffUserId = null,
    ) {}

    public static function from(AssessmentStatusTransition $transition, ?int $ownerId): self
    {
        $actor = ActorKind::of($transition->actor_user_id, $ownerId);

        return new self(
            $transition->from_status,
            $transition->to_status,
            $transition->verb,
            $transition->created_at,
            $actor,
            $actor === ActorKind::Staff ? $transition->actor_user_id : null,
        );
    }
}
