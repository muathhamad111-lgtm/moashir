<?php

declare(strict_types=1);

namespace App\Services\Entities\Timeline;

use App\Enums\Entities\EntityTimelineKind;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use UnexpectedValueException;

/**
 * One row of the timeline feed: which event, the id of the record it comes from, and when it happened (UTC).
 */
final readonly class TimelineRow
{
    public function __construct(public EntityTimelineKind $kind, public int $sourceId, public CarbonInterface $occurredAt) {}

    public static function from(mixed $row): self
    {
        $values = is_object($row) ? get_object_vars($row) : [];
        $kind = $values['kind'] ?? null;
        $sourceId = $values['source_id'] ?? null;
        $occurredAt = $values['occurred_at'] ?? null;

        if (! is_string($kind) || ! is_numeric($sourceId) || ! is_string($occurredAt)) {
            throw new UnexpectedValueException('A timeline row needs a kind, a source id and a date.');
        }

        return new self(EntityTimelineKind::from($kind), (int) $sourceId, Date::parse($occurredAt, 'UTC'));
    }
}
