<?php

declare(strict_types=1);

namespace App\Models\Platform;

use App\Enums\Platform\HousekeepingTask;
use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property HousekeepingTask $task
 * @property array<string, mixed> $counts
 * @property Carbon $started_at
 * @property Carbon $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class HousekeepingRun extends Model
{
    use AppendOnly;

    /**
     * @var list<string>
     */
    protected $fillable = ['task', 'counts', 'started_at', 'completed_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'task' => HousekeepingTask::class,
            'counts' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
