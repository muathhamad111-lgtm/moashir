<?php

declare(strict_types=1);

namespace App\Models\Award;

use App\Casts\OptionalEarnedScoreCast;
use App\Casts\OptionalPercentCast;
use App\Enums\Award\CommitteeAssignmentStatus;
use App\Enums\Award\CommitteeVoidReason;
use App\Models\Assessments\Assessment;
use App\Support\ValueObjects\EarnedScore;
use App\Support\ValueObjects\Percent;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $award_cycle_id
 * @property int $cycle_committee_assessor_id
 * @property int $assessment_id
 * @property CommitteeAssignmentStatus $status
 * @property int|null $replaces_assignment_id
 * @property Carbon|null $substitute_approved_at
 * @property int|null $substitute_approved_by_user_id
 * @property Carbon|null $started_at
 * @property Carbon|null $submitted_at
 * @property Carbon|null $returned_at
 * @property Percent|null $percent
 * @property EarnedScore|null $total_score
 * @property Carbon|null $voided_at
 * @property CommitteeVoidReason|null $void_reason
 * @property Carbon|null $overdue_notified_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read CycleCommitteeAssessor $member
 * @property-read Assessment $assessment
 * @property-read AwardCycle $awardCycle
 * @property-read CommitteeAssignment|null $replaces
 * @property-read CommitteeAssignment|null $replacedBy
 */
final class CommitteeAssignment extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['award_cycle_id', 'cycle_committee_assessor_id', 'assessment_id', 'status', 'replaces_assignment_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'award_cycle_id' => 'integer',
            'cycle_committee_assessor_id' => 'integer',
            'assessment_id' => 'integer',
            'status' => CommitteeAssignmentStatus::class,
            'replaces_assignment_id' => 'integer',
            'substitute_approved_at' => 'datetime',
            'substitute_approved_by_user_id' => 'integer',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'returned_at' => 'datetime',
            'percent' => OptionalPercentCast::class,
            'total_score' => OptionalEarnedScoreCast::class,
            'voided_at' => 'datetime',
            'void_reason' => CommitteeVoidReason::class,
            'overdue_notified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CycleCommitteeAssessor, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(CycleCommitteeAssessor::class, 'cycle_committee_assessor_id');
    }

    /**
     * @return BelongsTo<Assessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * @return BelongsTo<AwardCycle, $this>
     */
    public function awardCycle(): BelongsTo
    {
        return $this->belongsTo(AwardCycle::class);
    }

    /**
     * @return BelongsTo<CommitteeAssignment, $this>
     */
    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_assignment_id');
    }

    /**
     * @return HasOne<CommitteeAssignment, $this>
     */
    public function replacedBy(): HasOne
    {
        return $this->hasOne(self::class, 'replaces_assignment_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function live(Builder $query): void
    {
        $query->where('status', '<>', CommitteeAssignmentStatus::Voided);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->whereIn('status', [CommitteeAssignmentStatus::NotStarted, CommitteeAssignmentStatus::InProgress]);
    }

    public function isVoided(): bool
    {
        return $this->status === CommitteeAssignmentStatus::Voided;
    }

    public function isCompleted(): bool
    {
        return $this->status === CommitteeAssignmentStatus::Completed;
    }

    public function isPendingSubstitute(): bool
    {
        return $this->replaces_assignment_id !== null && $this->substitute_approved_at === null;
    }

    public function isOverdue(CarbonInterface $now, CarbonInterface $dueAt): bool
    {
        return $this->status->acceptsWork() && $dueAt->lessThan($now);
    }
}
