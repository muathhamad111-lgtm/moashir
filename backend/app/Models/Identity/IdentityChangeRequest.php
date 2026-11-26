<?php

declare(strict_types=1);

namespace App\Models\Identity;

use App\Enums\Identity\IdentityChangeKind;
use App\Enums\Identity\IdentityChangeStatus;
use App\Enums\Identity\IdentityField;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property int|null $submitted_by_user_id
 * @property string|null $new_name
 * @property string|null $new_national_id
 * @property string|null $reason
 * @property IdentityChangeKind $kind
 * @property IdentityChangeStatus $status
 * @property int|null $reviewed_by_user_id
 * @property string|null $review_note
 * @property Carbon|null $decided_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read User|null $submitter
 * @property-read User|null $reviewer
 */
final class IdentityChangeRequest extends Model
{
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'submitted_by_user_id', 'new_name', 'new_national_id', 'reason', 'status', 'kind'];

    /**
     * @var list<string>
     */
    protected $hidden = ['new_national_id'];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'submitted_by_user_id' => 'integer',
            'reviewed_by_user_id' => 'integer',
            'new_national_id' => 'encrypted',
            'status' => IdentityChangeStatus::class,
            'kind' => IdentityChangeKind::class,
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id')->withTrashed();
    }

    public function isPending(): bool
    {
        return $this->status === IdentityChangeStatus::Pending;
    }

    public function involves(int $userId): bool
    {
        return $this->user_id === $userId || $this->submitted_by_user_id === $userId;
    }

    /**
     * @return list<IdentityField>
     */
    public function fields(): array
    {
        return array_values(array_filter([
            $this->new_name !== null ? IdentityField::Name : null,
            $this->new_national_id !== null ? IdentityField::NationalId : null,
        ]));
    }
}
