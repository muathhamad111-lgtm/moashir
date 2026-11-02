<?php

declare(strict_types=1);

namespace App\Models\Award;

use App\Casts\LocalizedTextCast;
use App\Casts\PercentCast;
use App\Enums\Award\AwardCycleStatus;
use App\Enums\Award\CycleMemberRole;
use App\Enums\Criteria\ScaleKind;
use App\Models\Assessments\Assessment;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Entities\Region;
use App\Models\Identity\User;
use App\Support\ValueObjects\LocalizedText;
use App\Support\ValueObjects\Percent;
use Carbon\CarbonInterface;
use Database\Factories\Award\AwardCycleFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property LocalizedText $name
 * @property int $year
 * @property AwardCycleStatus $status
 * @property int $criteria_version_id
 * @property ScaleKind $scale_kind
 * @property Carbon $submission_start
 * @property Carbon $submission_end
 * @property Carbon $committee_start
 * @property Carbon $committee_end
 * @property Carbon|null $announcement_at
 * @property int $min_entity_age_years
 * @property Percent $disqualification_score
 * @property int $initial_review_sla_days
 * @property bool $participation_certificate_enabled
 * @property int $created_by_user_id
 * @property Carbon|null $opened_at
 * @property Carbon|null $submissions_closed_at
 * @property Carbon|null $closed_at
 * @property AwardCycleStatus|null $closed_from_status
 * @property Carbon|null $announced_at
 * @property int|null $announced_by_user_id
 * @property Carbon|null $committee_started_at
 * @property Carbon|null $national_approved_at
 * @property int|null $national_approved_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read CriteriaVersion $criteriaVersion
 * @property-read Collection<int, Region> $regions
 * @property-read Collection<int, AwardCycleMember> $members
 * @property-read Collection<int, AwardCycleAssessor> $initialAssessors
 * @property-read Collection<int, CycleCommitteeAssessor> $committeeMembers
 * @property-read User $createdBy
 */
final class AwardCycle extends Model
{
    /** @use HasFactory<AwardCycleFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name', 'year', 'status', 'criteria_version_id', 'scale_kind', 'submission_start', 'submission_end',
        'committee_start', 'committee_end', 'announcement_at', 'min_entity_age_years', 'disqualification_score',
        'initial_review_sla_days', 'participation_certificate_enabled', 'created_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => LocalizedTextCast::class,
            'year' => 'integer',
            'status' => AwardCycleStatus::class,
            'criteria_version_id' => 'integer',
            'scale_kind' => ScaleKind::class,
            'submission_start' => 'datetime',
            'submission_end' => 'datetime',
            'committee_start' => 'datetime',
            'committee_end' => 'datetime',
            'announcement_at' => 'datetime',
            'min_entity_age_years' => 'integer',
            'disqualification_score' => PercentCast::class,
            'initial_review_sla_days' => 'integer',
            'participation_certificate_enabled' => 'boolean',
            'created_by_user_id' => 'integer',
            'opened_at' => 'datetime',
            'submissions_closed_at' => 'datetime',
            'closed_at' => 'datetime',
            'closed_from_status' => AwardCycleStatus::class,
            'announced_at' => 'datetime',
            'announced_by_user_id' => 'integer',
            'committee_started_at' => 'datetime',
            'national_approved_at' => 'datetime',
            'national_approved_by_user_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CriteriaVersion, $this>
     */
    public function criteriaVersion(): BelongsTo
    {
        return $this->belongsTo(CriteriaVersion::class);
    }

    /**
     * @return BelongsToMany<Region, $this>
     */
    public function regions(): BelongsToMany
    {
        return $this->belongsToMany(Region::class, 'award_cycle_regions')->orderBy('sort_order')->orderBy('regions.id');
    }

    /**
     * @return HasMany<AwardCycleMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(AwardCycleMember::class)->orderBy('id');
    }

    /**
     * @return HasMany<AwardCycleAssessor, $this>
     */
    public function initialAssessors(): HasMany
    {
        return $this->hasMany(AwardCycleAssessor::class)->orderBy('id');
    }

    /**
     * @return HasMany<CycleCommitteeAssessor, $this>
     */
    public function committeeMembers(): HasMany
    {
        return $this->hasMany(CycleCommitteeAssessor::class)->orderBy('id');
    }

    /**
     * @return HasMany<Assessment, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id')->withTrashed();
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function running(Builder $query): void
    {
        $query->whereNotIn('status', [AwardCycleStatus::Draft, AwardCycleStatus::Closed]);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withMember(Builder $query, int $userId, CycleMemberRole ...$roles): void
    {
        $query->whereHas('members', static fn (Builder $member): Builder => $member
            ->where('user_id', $userId)
            ->whereIn('role', array_map(static fn (CycleMemberRole $role): string => $role->value, $roles)));
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withInitialAssessor(Builder $query, int $userId): void
    {
        $query->whereHas('initialAssessors', static fn (Builder $link): Builder => $link->where('assessor_user_id', $userId));
    }

    public function acceptsApplicationsAt(CarbonInterface $moment): bool
    {
        return $this->status->acceptsApplications()
            && $moment->greaterThanOrEqualTo($this->submission_start)
            && $moment->lessThanOrEqualTo($this->submission_end);
    }

    public function covers(int $regionId): bool
    {
        return $this->regions->contains('id', $regionId);
    }

    public function isAnnounced(): bool
    {
        return $this->announced_at !== null;
    }

    public function isNationalApproved(): bool
    {
        return $this->national_approved_at !== null;
    }
}
