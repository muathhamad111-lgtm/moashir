<?php

declare(strict_types=1);

namespace App\Models\Award;

use App\Enums\Award\CycleMemberRole;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $award_cycle_id
 * @property int $user_id
 * @property CycleMemberRole $role
 * @property int|null $added_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 */
final class AwardCycleMember extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['award_cycle_id', 'user_id', 'role', 'added_by_user_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'award_cycle_id' => 'integer',
            'user_id' => 'integer',
            'role' => CycleMemberRole::class,
            'added_by_user_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
