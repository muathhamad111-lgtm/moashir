<?php

declare(strict_types=1);

namespace App\Models\Award;

use App\Casts\PercentCast;
use App\Enums\Award\FilteringOutcome;
use App\Exceptions\Persistence\AppendOnlyViolation;
use App\Models\Identity\User;
use App\Support\ValueObjects\Percent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $assessment_id
 * @property int $award_cycle_id
 * @property int $result_snapshot_id
 * @property FilteringOutcome $outcome
 * @property Percent $initial_percent
 * @property Percent $threshold_percent
 * @property bool $is_override
 * @property string|null $reason
 * @property string|null $note
 * @property int $decided_by_user_id
 * @property Carbon $created_at
 * @property-read User $decidedBy
 */
final class FilteringDecision extends Model
{
    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'assessment_id', 'award_cycle_id', 'result_snapshot_id', 'outcome', 'initial_percent', 'threshold_percent',
        'is_override', 'reason', 'note', 'decided_by_user_id',
    ];

    protected static function booted(): void
    {
        self::updating(static fn (): never => throw AppendOnlyViolation::forModel(self::class));
        self::deleting(static fn (): never => throw AppendOnlyViolation::forModel(self::class));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assessment_id' => 'integer',
            'award_cycle_id' => 'integer',
            'result_snapshot_id' => 'integer',
            'outcome' => FilteringOutcome::class,
            'initial_percent' => PercentCast::class,
            'threshold_percent' => PercentCast::class,
            'is_override' => 'boolean',
            'decided_by_user_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id')->withTrashed();
    }
}
