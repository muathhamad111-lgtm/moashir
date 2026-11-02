<?php

declare(strict_types=1);

namespace App\Models\Award;

use App\Enums\Award\SecretaryDecisionKind;
use App\Models\Concerns\AppendOnly;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $award_cycle_id
 * @property int|null $assessment_id
 * @property int|null $region_id
 * @property SecretaryDecisionKind $kind
 * @property int|null $committee_average_id
 * @property int|null $committee_assignment_id
 * @property string|null $note
 * @property int $decided_by_user_id
 * @property Carbon $created_at
 * @property-read User $decidedBy
 */
final class SecretaryDecision extends Model
{
    use AppendOnly;

    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'award_cycle_id', 'assessment_id', 'region_id', 'kind', 'committee_average_id', 'committee_assignment_id', 'note',
        'decided_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'award_cycle_id' => 'integer',
            'assessment_id' => 'integer',
            'region_id' => 'integer',
            'kind' => SecretaryDecisionKind::class,
            'committee_average_id' => 'integer',
            'committee_assignment_id' => 'integer',
            'decided_by_user_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id')->withTrashed();
    }
}
