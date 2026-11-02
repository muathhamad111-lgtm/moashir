<?php

declare(strict_types=1);

namespace App\Models\Award;

use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $award_cycle_id
 * @property int $assessor_user_id
 * @property bool $is_active
 * @property int|null $added_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $assessor
 */
final class AwardCycleAssessor extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['award_cycle_id', 'assessor_user_id', 'is_active', 'added_by_user_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'award_cycle_id' => 'integer',
            'assessor_user_id' => 'integer',
            'is_active' => 'boolean',
            'added_by_user_id' => 'integer',
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
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
