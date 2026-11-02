<?php

declare(strict_types=1);

namespace App\Models\Award;

use App\Enums\Award\CommitteeDiscussionMode;
use App\Enums\Award\CommitteeDiscussionStatus;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $award_cycle_id
 * @property int $assessment_id
 * @property CommitteeDiscussionMode $mode
 * @property CommitteeDiscussionStatus $status
 * @property Carbon|null $deadline_at
 * @property string|null $agreed_outcome
 * @property string|null $summary
 * @property int $opened_by_user_id
 * @property int|null $closed_by_user_id
 * @property Carbon|null $closed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Assessment $assessment
 * @property-read Collection<int, CommitteeDiscussionEntry> $currentEntries
 * @property-read User $openedBy
 */
final class CommitteeDiscussion extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['award_cycle_id', 'assessment_id', 'mode', 'status', 'deadline_at', 'agreed_outcome', 'opened_by_user_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'award_cycle_id' => 'integer',
            'assessment_id' => 'integer',
            'mode' => CommitteeDiscussionMode::class,
            'status' => CommitteeDiscussionStatus::class,
            'deadline_at' => 'datetime',
            'opened_by_user_id' => 'integer',
            'closed_by_user_id' => 'integer',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Assessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id')->withTrashed();
    }

    /**
     * @return HasMany<CommitteeDiscussionEntry, $this>
     */
    public function currentEntries(): HasMany
    {
        return $this->hasMany(CommitteeDiscussionEntry::class)->whereNull('superseded_at')->orderBy('id');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->where('status', CommitteeDiscussionStatus::Open);
    }

    public function isOpen(): bool
    {
        return $this->status === CommitteeDiscussionStatus::Open;
    }

    public function acceptsEntriesAt(CarbonInterface $moment): bool
    {
        return $this->isOpen() && (! $this->deadline_at instanceof Carbon || $moment->lessThanOrEqualTo($this->deadline_at));
    }
}
