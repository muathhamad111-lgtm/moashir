<?php

declare(strict_types=1);

namespace App\Models\Criteria;

use App\Enums\Criteria\CriteriaEditKind;
use App\Enums\Criteria\CriteriaEditScope;
use App\Models\Concerns\AppendOnly;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property CriteriaEditKind $kind
 * @property CriteriaEditScope $scope
 * @property int $from_version_id
 * @property int|null $to_version_id
 * @property list<array<string, mixed>> $changes
 * @property int $affected_open_count
 * @property string|null $reason
 * @property int $edited_by_user_id
 * @property Carbon $created_at
 * @property-read CriteriaVersion $fromVersion
 * @property-read CriteriaVersion|null $toVersion
 * @property-read User|null $editor
 */
final class CriteriaEditEvent extends Model
{
    use AppendOnly;

    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['kind', 'scope', 'from_version_id', 'to_version_id', 'changes', 'affected_open_count', 'reason', 'edited_by_user_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => CriteriaEditKind::class,
            'scope' => CriteriaEditScope::class,
            'changes' => 'array',
            'affected_open_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CriteriaVersion, $this>
     */
    public function fromVersion(): BelongsTo
    {
        return $this->belongsTo(CriteriaVersion::class, 'from_version_id')->withTrashed();
    }

    /**
     * @return BelongsTo<CriteriaVersion, $this>
     */
    public function toVersion(): BelongsTo
    {
        return $this->belongsTo(CriteriaVersion::class, 'to_version_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by_user_id');
    }
}
