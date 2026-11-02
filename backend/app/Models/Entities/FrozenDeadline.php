<?php

declare(strict_types=1);

namespace App\Models\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $entity_id
 * @property string $subject_type
 * @property int $subject_id
 * @property string $attribute
 * @property Carbon $due_at
 * @property int $remaining_seconds
 * @property Carbon $frozen_at
 * @property Carbon $created_at
 * @property-read Model|null $subject
 */
final class FrozenDeadline extends Model
{
    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['entity_id', 'subject_type', 'subject_id', 'attribute', 'due_at', 'remaining_seconds', 'frozen_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entity_id' => 'integer',
            'subject_id' => 'integer',
            'due_at' => 'datetime',
            'remaining_seconds' => 'integer',
            'frozen_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
