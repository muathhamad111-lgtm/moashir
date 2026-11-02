<?php

declare(strict_types=1);

namespace App\Models\Identity;

use App\Enums\Identity\EmailChangePolicy;
use App\Enums\Identity\EmailChangeStatus;
use App\Models\Entities\Entity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $entity_id
 * @property string $old_email
 * @property string $new_email
 * @property int|null $otp_code_id
 * @property Carbon|null $otp_verified_at
 * @property EmailChangePolicy $policy
 * @property EmailChangeStatus $status
 * @property int|null $decided_by_user_id
 * @property string|null $decision_reason
 * @property Carbon|null $applied_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read Entity|null $entity
 */
final class EmailChangeRequest extends Model
{
    protected $fillable = ['user_id', 'entity_id', 'old_email', 'new_email', 'otp_code_id', 'policy', 'status'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'policy' => EmailChangePolicy::class,
            'status' => EmailChangeStatus::class,
            'otp_verified_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<OtpCode, $this>
     */
    public function otpCode(): BelongsTo
    {
        return $this->belongsTo(OtpCode::class);
    }

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class)->withTrashed();
    }

    public function isAwaitingApproval(): bool
    {
        return $this->status === EmailChangeStatus::AwaitingApproval;
    }
}
