<?php

declare(strict_types=1);

namespace App\Models\Award;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $award_cycle_id
 * @property int $region_id
 * @property int $winners_count
 * @property int $approved_by_user_id
 * @property Carbon $approved_at
 * @property Carbon|null $unapproved_at
 * @property int|null $unapproved_by_user_id
 * @property string|null $unapprove_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class AwardRegionApproval extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['award_cycle_id', 'region_id', 'winners_count', 'approved_by_user_id', 'approved_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'award_cycle_id' => 'integer',
            'region_id' => 'integer',
            'winners_count' => 'integer',
            'approved_by_user_id' => 'integer',
            'approved_at' => 'datetime',
            'unapproved_at' => 'datetime',
            'unapproved_by_user_id' => 'integer',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function current(Builder $query): void
    {
        $query->whereNull('unapproved_at');
    }
}
