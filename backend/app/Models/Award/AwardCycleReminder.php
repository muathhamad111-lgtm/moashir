<?php

declare(strict_types=1);

namespace App\Models\Award;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $award_cycle_id
 * @property int $days_before
 * @property int $recipients_count
 * @property Carbon $sent_at
 */
final class AwardCycleReminder extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = ['award_cycle_id', 'days_before', 'recipients_count', 'sent_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'award_cycle_id' => 'integer',
            'days_before' => 'integer',
            'recipients_count' => 'integer',
            'sent_at' => 'datetime',
        ];
    }
}
