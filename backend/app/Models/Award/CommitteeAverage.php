<?php

declare(strict_types=1);

namespace App\Models\Award;

use App\Casts\PercentCast;
use App\Models\Concerns\AppendOnly;
use App\Support\ValueObjects\Percent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $assessment_id
 * @property int $award_cycle_id
 * @property int $version
 * @property Percent $percent
 * @property int $member_count
 * @property list<array{assignment_id: int, percent: string}> $inputs
 * @property string $inputs_digest
 * @property bool $is_manual
 * @property int|null $computed_by_user_id
 * @property Carbon $created_at
 */
final class CommitteeAverage extends Model
{
    use AppendOnly;

    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'assessment_id', 'award_cycle_id', 'version', 'percent', 'member_count', 'inputs', 'inputs_digest', 'is_manual',
        'computed_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assessment_id' => 'integer',
            'award_cycle_id' => 'integer',
            'version' => 'integer',
            'percent' => PercentCast::class,
            'member_count' => 'integer',
            'inputs' => 'array',
            'is_manual' => 'boolean',
            'computed_by_user_id' => 'integer',
        ];
    }
}
