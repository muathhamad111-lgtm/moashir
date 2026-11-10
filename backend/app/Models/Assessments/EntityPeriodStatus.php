<?php

declare(strict_types=1);

namespace App\Models\Assessments;

use App\Enums\Periods\EntityPeriodState;
use App\Models\Entities\Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $entity_id
 * @property int $assessment_period_id
 * @property EntityPeriodState $status
 * @property int|null $assessment_id
 * @property Carbon $decided_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read AssessmentPeriod $period
 * @property-read Entity $entity
 */
final class EntityPeriodStatus extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['entity_id', 'assessment_period_id', 'status', 'assessment_id', 'decided_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entity_id' => 'integer',
            'assessment_period_id' => 'integer',
            'status' => EntityPeriodState::class,
            'assessment_id' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AssessmentPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriod::class, 'assessment_period_id');
    }

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class)->withTrashed();
    }
}
