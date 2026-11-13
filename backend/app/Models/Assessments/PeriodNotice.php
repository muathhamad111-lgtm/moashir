<?php

declare(strict_types=1);

namespace App\Models\Assessments;

use App\Enums\Notifications\NotificationCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $assessment_period_id
 * @property int $entity_id
 * @property NotificationCode $code
 * @property int $threshold_days
 * @property Carbon $sent_at
 */
final class PeriodNotice extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = ['assessment_period_id', 'entity_id', 'code', 'threshold_days', 'sent_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assessment_period_id' => 'integer',
            'entity_id' => 'integer',
            'code' => NotificationCode::class,
            'threshold_days' => 'integer',
            'sent_at' => 'datetime',
        ];
    }
}
