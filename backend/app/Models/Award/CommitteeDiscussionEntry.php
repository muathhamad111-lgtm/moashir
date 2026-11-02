<?php

declare(strict_types=1);

namespace App\Models\Award;

use App\Enums\Award\DiscussionEntryKind;
use App\Exceptions\Persistence\AppendOnlyViolation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $committee_discussion_id
 * @property int $cycle_committee_assessor_id
 * @property DiscussionEntryKind $kind
 * @property string|null $body
 * @property Carbon|null $superseded_at
 * @property Carbon $created_at
 * @property-read CycleCommitteeAssessor $member
 */
final class CommitteeDiscussionEntry extends Model
{
    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['committee_discussion_id', 'cycle_committee_assessor_id', 'kind', 'body'];

    protected static function booted(): void
    {
        self::updating(static function (self $entry): void {
            if ($entry->getOriginal('superseded_at') !== null || array_keys($entry->getDirty()) !== ['superseded_at']) {
                throw AppendOnlyViolation::forModel(self::class);
            }
        });

        self::deleting(static fn (): never => throw AppendOnlyViolation::forModel(self::class));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'committee_discussion_id' => 'integer',
            'cycle_committee_assessor_id' => 'integer',
            'kind' => DiscussionEntryKind::class,
            'superseded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CycleCommitteeAssessor, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(CycleCommitteeAssessor::class, 'cycle_committee_assessor_id');
    }
}
