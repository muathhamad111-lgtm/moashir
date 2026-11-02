<?php

declare(strict_types=1);

namespace App\Models\Assessments;

use App\Enums\Assessments\AssignmentStatus;
use App\Exceptions\Persistence\AppendOnlyViolation;
use App\Models\Identity\User;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $assessment_id
 * @property int $assessor_user_id
 * @property int|null $assigned_by_user_id
 * @property AssignmentStatus $status
 * @property string|null $reason
 * @property Carbon|null $started_at
 * @property Carbon|null $ended_at
 * @property string|null $end_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $assessor
 * @property-read User|null $assignedBy
 */
final class AssessmentAssignment extends Model
{
    private const array MUTABLE = ['status', 'started_at', 'ended_at', 'end_reason', 'updated_at'];

    /**
     * @var list<string>
     */
    protected $fillable = ['assessment_id', 'assessor_user_id', 'assigned_by_user_id', 'status', 'reason'];

    protected static function booted(): void
    {
        self::updating(static function (self $assignment): void {
            $original = AssignmentStatus::tryFrom(ScalarValue::string($assignment->getRawOriginal('status')));

            if ($original !== AssignmentStatus::Active || array_diff(array_keys($assignment->getDirty()), self::MUTABLE) !== []) {
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
            'assessment_id' => 'integer',
            'assessor_user_id' => 'integer',
            'assigned_by_user_id' => 'integer',
            'status' => AssignmentStatus::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id')->withTrashed();
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', AssignmentStatus::Active);
    }

    public function isHeldBy(int $userId): bool
    {
        return $this->status === AssignmentStatus::Active && $this->assessor_user_id === $userId;
    }
}
