<?php

declare(strict_types=1);

namespace App\Data\Entities;

use App\Data\Identity\StaffActor;
use App\Enums\Entities\EntityTimelineKind;
use Carbon\CarbonInterface;

/**
 * One event of an entity's account timeline. Only the fields its kind carries are set: a reason for a staff
 * lifecycle decision, the status and changed fields of a profile-change request, the field change itself, or
 * the addresses of an e-mail change.
 */
final readonly class EntityTimelineEntry
{
    /**
     * @param  list<string>  $fields
     * @param  array{old: string, new: string}|null  $email
     */
    public function __construct(
        public EntityTimelineKind $kind,
        public int $sourceId,
        public CarbonInterface $occurredAt,
        public ?StaffActor $actor = null,
        public ?string $status = null,
        public ?string $reason = null,
        public array $fields = [],
        public ?StaffProfileChangeEntry $change = null,
        public ?array $email = null,
    ) {}
}
