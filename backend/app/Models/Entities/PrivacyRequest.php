<?php

declare(strict_types=1);

namespace App\Models\Entities;

use App\Enums\Entities\PrivacyRequestKind;
use App\Enums\Entities\PrivacyRequestStatus;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $entity_id
 * @property PrivacyRequestKind $kind
 * @property PrivacyRequestStatus $status
 * @property string $details
 * @property int $created_by_user_id
 * @property int|null $decided_by_user_id
 * @property string|null $decision_note
 * @property Carbon|null $decided_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Entity $entity
 * @property-read User $createdBy
 * @property-read User|null $decidedBy
 */
final class PrivacyRequest extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'open'];

    /**
     * @var list<string>
     */
    protected $fillable = ['entity_id', 'kind', 'details', 'created_by_user_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entity_id' => 'integer',
            'kind' => PrivacyRequestKind::class,
            'status' => PrivacyRequestStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id')->withTrashed();
    }

    public function isOpen(): bool
    {
        return $this->status === PrivacyRequestStatus::Open;
    }
}
