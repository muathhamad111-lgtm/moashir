<?php

declare(strict_types=1);

namespace App\Models\Award;

use App\Enums\Award\CommitteeMembershipStatus;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $award_cycle_id
 * @property int $assessor_user_id
 * @property CommitteeMembershipStatus $status
 * @property int|null $replaces_id
 * @property int|null $appointed_by_user_id
 * @property Carbon|null $disabled_at
 * @property string|null $disabled_reason
 * @property int|null $disabled_by_user_id
 * @property Carbon|null $removed_at
 * @property Carbon|null $overdue_notified_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $assessor
 * @property-read AwardCycle $awardCycle
 * @property-read CycleCommitteeAssessor|null $replaces
 * @property-read CycleCommitteeAssessor|null $replacedBy
 */
final class CycleCommitteeAssessor extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['award_cycle_id', 'assessor_user_id', 'status', 'replaces_id', 'appointed_by_user_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'award_cycle_id' => 'integer',
            'assessor_user_id' => 'integer',
            'status' => CommitteeMembershipStatus::class,
            'replaces_id' => 'integer',
            'appointed_by_user_id' => 'integer',
            'disabled_at' => 'datetime',
            'disabled_by_user_id' => 'integer',
            'removed_at' => 'datetime',
            'overdue_notified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessor_user_id')->withTrashed();
    }

    /**
     * @return BelongsTo<AwardCycle, $this>
     */
    public function awardCycle(): BelongsTo
    {
        return $this->belongsTo(AwardCycle::class);
    }

    /**
     * @return BelongsTo<CycleCommitteeAssessor, $this>
     */
    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_id');
    }

    /**
     * @return HasOne<CycleCommitteeAssessor, $this>
     */
    public function replacedBy(): HasOne
    {
        return $this->hasOne(self::class, 'replaces_id');
    }

    /**
     * @return HasMany<CommitteeAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(CommitteeAssignment::class)->orderBy('id');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function seated(Builder $query): void
    {
        $query->where('status', '<>', CommitteeMembershipStatus::Removed);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', CommitteeMembershipStatus::Active);
    }

    public function isHeldBy(int $userId): bool
    {
        return $this->assessor_user_id === $userId;
    }

    public function isSubstitute(): bool
    {
        return $this->replaces_id !== null;
    }
}
