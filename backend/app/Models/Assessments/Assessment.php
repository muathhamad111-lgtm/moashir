<?php

declare(strict_types=1);

namespace App\Models\Assessments;

use App\Casts\AssessmentStateCast;
use App\Contracts\Assessments\AssessmentState;
use App\Contracts\Assessments\ReviewedAssessmentState;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Assessments\AssignmentStatus;
use App\Enums\Certificates\CertificateKind;
use App\Enums\Criteria\ScaleKind;
use App\Models\Award\AwardCycle;
use App\Models\Award\AwardRanking;
use App\Models\Award\CommitteeAssignment;
use App\Models\Award\CommitteeAverage;
use App\Models\Award\FilteringDecision;
use App\Models\Certificates\Certificate;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Entities\Entity;
use App\Models\Entities\Region;
use App\Models\Finance\Payment;
use App\Support\ValueObjects\SearchTerm;
use Carbon\CarbonInterface;
use Database\Factories\Assessments\AssessmentFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property string $request_number
 * @property int $entity_id
 * @property AssessmentPath $path
 * @property AssessmentState $status
 * @property int $criteria_version_id
 * @property ScaleKind $scale_kind
 * @property int|null $current_result_snapshot_id
 * @property Carbon|null $submitted_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $due_at
 * @property Carbon|null $criteria_update_pending_since
 * @property int $criteria_update_redispatches
 * @property int|null $payment_id
 * @property int|null $review_sla_days
 * @property Carbon|null $overdue_notified_at
 * @property Carbon|null $refunded_at
 * @property int|null $award_cycle_id
 * @property int|null $region_id
 * @property array<string, mixed>|null $condition_failure
 * @property Carbon|null $withdrawn_at
 * @property int|null $committee_average_id
 * @property int|null $period_id
 * @property Carbon|null $period_assigned_at
 * @property Carbon|null $paid_draft_reminded_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Carbon|null $entity_activity_at
 * @property-read Entity $entity
 * @property-read CriteriaVersion $criteriaVersion
 * @property-read AssessmentResultSnapshot|null $currentResult
 * @property-read Collection<int, AssessmentScore> $scores
 * @property-read Collection<int, AssessmentStatusTransition> $transitions
 * @property-read Payment|null $payment
 * @property-read AssessmentAssignment|null $activeAssignment
 * @property-read Collection<int, AssessmentAssignment> $assignments
 * @property-read AssessmentCompletionRequest|null $openCompletionRequest
 * @property-read AwardCycle|null $awardCycle
 * @property-read Region|null $region
 * @property-read FilteringDecision|null $latestFilteringDecision
 * @property-read CommitteeAverage|null $committeeAverage
 * @property-read AwardRanking|null $ranking
 * @property-read AssessmentPeriod|null $period
 * @property-read Certificate|null $certificate
 */
final class Assessment extends Model
{
    /** @use HasFactory<AssessmentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'request_number', 'entity_id', 'path', 'status', 'criteria_version_id', 'scale_kind', 'due_at', 'payment_id',
        'award_cycle_id', 'region_id', 'condition_failure',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entity_id' => 'integer',
            'path' => AssessmentPath::class,
            'status' => AssessmentStateCast::class,
            'criteria_version_id' => 'integer',
            'scale_kind' => ScaleKind::class,
            'current_result_snapshot_id' => 'integer',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'due_at' => 'datetime',
            'criteria_update_pending_since' => 'datetime',
            'criteria_update_redispatches' => 'integer',
            'payment_id' => 'integer',
            'review_sla_days' => 'integer',
            'overdue_notified_at' => 'datetime',
            'refunded_at' => 'datetime',
            'award_cycle_id' => 'integer',
            'region_id' => 'integer',
            'condition_failure' => 'array',
            'withdrawn_at' => 'datetime',
            'committee_average_id' => 'integer',
            'period_id' => 'integer',
            'period_assigned_at' => 'datetime',
            'paid_draft_reminded_at' => 'datetime',
            'entity_activity_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class)->withTrashed();
    }

    /**
     * @return BelongsTo<CriteriaVersion, $this>
     */
    public function criteriaVersion(): BelongsTo
    {
        return $this->belongsTo(CriteriaVersion::class);
    }

    /**
     * @return BelongsTo<AssessmentPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriod::class, 'period_id');
    }

    /**
     * @return HasOne<Certificate, $this>
     */
    public function certificate(): HasOne
    {
        return $this->hasOne(Certificate::class)->ofMany(
            ['id' => 'max'],
            static function (Builder $certificates): void {
                $certificates->where('kind', CertificateKind::Accredited);
            },
        );
    }

    /**
     * @return BelongsTo<AssessmentResultSnapshot, $this>
     */
    public function currentResult(): BelongsTo
    {
        return $this->belongsTo(AssessmentResultSnapshot::class, 'current_result_snapshot_id');
    }

    /**
     * @return HasMany<AssessmentScore, $this>
     */
    public function scores(): HasMany
    {
        return $this->hasMany(AssessmentScore::class);
    }

    /**
     * @return HasMany<AssessmentStatusTransition, $this>
     */
    public function transitions(): HasMany
    {
        return $this->hasMany(AssessmentStatusTransition::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return HasMany<AssessmentAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(AssessmentAssignment::class)->orderBy('id');
    }

    /**
     * @return HasOne<AssessmentAssignment, $this>
     */
    public function activeAssignment(): HasOne
    {
        return $this->hasOne(AssessmentAssignment::class)->active();
    }

    /**
     * @return HasOne<AssessmentCompletionRequest, $this>
     */
    public function openCompletionRequest(): HasOne
    {
        return $this->hasOne(AssessmentCompletionRequest::class)->open();
    }

    /**
     * @return BelongsTo<AwardCycle, $this>
     */
    public function awardCycle(): BelongsTo
    {
        return $this->belongsTo(AwardCycle::class);
    }

    /**
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * @return HasOne<FilteringDecision, $this>
     */
    public function latestFilteringDecision(): HasOne
    {
        return $this->hasOne(FilteringDecision::class)->latestOfMany();
    }

    /**
     * @return BelongsTo<CommitteeAverage, $this>
     */
    public function committeeAverage(): BelongsTo
    {
        return $this->belongsTo(CommitteeAverage::class);
    }

    /**
     * @return HasMany<CommitteeAssignment, $this>
     */
    public function committeeAssignments(): HasMany
    {
        return $this->hasMany(CommitteeAssignment::class)->orderBy('id');
    }

    /**
     * @return HasOne<AwardRanking, $this>
     */
    public function ranking(): HasOne
    {
        return $this->hasOne(AwardRanking::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ofPath(Builder $query, AssessmentPath $path): void
    {
        $query->where('path', $path);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->where(static function (Builder $inner): void {
            foreach (AssessmentPath::cases() as $path) {
                $inner->orWhere(static fn (Builder $byPath): Builder => $byPath
                    ->where('path', $path)
                    ->whereIn('status', $path->openStateValues()));
            }
        });
    }

    /**
     * @param  Builder<self>  $query
     * @param  list<string>  $maskedStatuses
     */
    #[Scope]
    protected function openForViewer(Builder $query, array $maskedStatuses): void
    {
        $query->where(static fn (Builder $visible): Builder => $visible
            ->open()
            ->orWhere(static fn (Builder $masked): Builder => $masked
                ->where('path', AssessmentPath::Advanced)
                ->whereIn('status', $maskedStatuses)
                ->whereDoesntHave('awardCycle', static fn (Builder $cycle): Builder => $cycle->whereNotNull('announced_at'))));
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function exposingResult(Builder $query): void
    {
        $query->whereNotNull('current_result_snapshot_id')->where(static function (Builder $byPath): void {
            foreach (AssessmentPath::cases() as $path) {
                $byPath->orWhere(static fn (Builder $results): Builder => $results
                    ->where('path', $path)
                    ->whereIn('status', $path->resultStateValues())
                    ->when($path->publishesResultsOnAnnouncement(), static fn (Builder $announced): Builder => $announced
                        ->whereHas('awardCycle', static fn (Builder $cycle): Builder => $cycle->whereNotNull('announced_at'))));
            }
        });
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function followingCriteriaEdits(Builder $query): void
    {
        $query->open()->whereIn('path', array_values(array_map(
            static fn (AssessmentPath $path): string => $path->value,
            array_filter(AssessmentPath::cases(), static fn (AssessmentPath $path): bool => $path->followsCriteriaEdits()),
        )));
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function inCycle(Builder $query, int $cycleId): void
    {
        $query->where('path', AssessmentPath::Advanced)->where('award_cycle_id', $cycleId);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function assignedTo(Builder $query, int $assessorId): void
    {
        $query->whereHas('assignments', static fn (Builder $assignment): Builder => $assignment->active()->where('assessor_user_id', $assessorId));
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function reviewedBy(Builder $query, int $assessorId): void
    {
        $query->whereHas('assignments', static fn (Builder $assignment): Builder => $assignment
            ->where('assessor_user_id', $assessorId)
            ->whereIn('status', [AssignmentStatus::Active, AssignmentStatus::Completed]));
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ownedBy(Builder $query, int $entityId): void
    {
        $query->where('entity_id', $entityId);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withEntityActivity(Builder $query): void
    {
        $query->select('assessments.*')->selectRaw(
            'greatest(assessments.created_at, '
            .'(select max(updated_at) from assessment_cv_entries where assessment_cv_entries.assessment_id = assessments.id), '
            .'(select max(updated_at) from evidence_files where evidence_files.assessment_id = assessments.id)) as entity_activity_at',
        );
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function matchingSearch(Builder $query, SearchTerm $term): void
    {
        $query->where(static fn (Builder $inner): Builder => $inner
            ->whereRaw('lower(request_number) like ?', [$term->containsPattern()])
            ->orWhereHas('entity', static fn (Builder $entity): Builder => $entity->notErased()->matchingSearch($term)));
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function isOwnedBy(?int $entityId): bool
    {
        return $entityId !== null && $this->entity_id === $entityId;
    }

    public function isFrozen(): bool
    {
        return $this->entity->trashed();
    }

    public function awaitsCriteriaUpdate(): bool
    {
        return $this->criteria_update_pending_since !== null;
    }

    public function hasResult(): bool
    {
        return $this->current_result_snapshot_id !== null;
    }

    public function exposesResult(): bool
    {
        return $this->hasResult() && $this->status->exposesResult() && (! $this->path->publishesResultsOnAnnouncement() || $this->isAnnounced());
    }

    public function isAnnounced(): bool
    {
        return $this->loadMissing('awardCycle')->awardCycle?->isAnnounced() === true;
    }

    public function isAccredited(): bool
    {
        return $this->path === AssessmentPath::Accredited;
    }

    public function isAdvanced(): bool
    {
        return $this->path === AssessmentPath::Advanced;
    }

    public function isAdvancedDraft(): bool
    {
        return $this->status === AdvancedAssessmentStatus::Draft;
    }

    public function followsCriteriaEdits(): bool
    {
        return $this->isOpen() && $this->path->followsCriteriaEdits();
    }

    public function isOverdue(CarbonInterface $now): bool
    {
        return $this->due_at instanceof Carbon
            && $this->status instanceof ReviewedAssessmentState
            && $this->status->runsReviewClock()
            && $this->due_at->lessThan($now);
    }
}
