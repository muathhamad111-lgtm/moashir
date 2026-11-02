<?php

declare(strict_types=1);

namespace App\Models\Entities;

use App\Enums\Entities\EntityProfileField;
use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $entity_id
 * @property int|null $changed_by_user_id
 * @property EntityProfileField $field
 * @property mixed $old_value
 * @property mixed $new_value
 * @property Carbon $created_at
 * @property-read Entity $entity
 */
final class EntityProfileChange extends Model
{
    use AppendOnly;

    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['entity_id', 'changed_by_user_id', 'field', 'old_value', 'new_value'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'field' => EntityProfileField::class,
            'old_value' => 'json',
            'new_value' => 'json',
        ];
    }

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class)->withTrashed();
    }
}
