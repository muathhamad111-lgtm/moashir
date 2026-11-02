<?php

declare(strict_types=1);

namespace App\Models\Identity;

use App\Enums\Identity\InvitationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property int $user_id
 * @property string $token_hash
 * @property InvitationStatus $status
 * @property int $invited_by_user_id
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property-read User $user
 */
final class UserInvitation extends Model implements Auditable
{
    use AuditableTrait;

    protected $fillable = ['user_id', 'token_hash', 'status', 'invited_by_user_id', 'expires_at'];

    protected $hidden = ['token_hash'];

    /**
     * @var list<string>
     */
    protected array $auditExclude = ['token_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvitationStatus::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function isUsable(): bool
    {
        return $this->status === InvitationStatus::Pending && $this->expires_at->isFuture();
    }
}
