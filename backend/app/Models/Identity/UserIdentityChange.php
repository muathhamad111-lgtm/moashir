<?php

declare(strict_types=1);

namespace App\Models\Identity;

use App\Enums\Identity\IdentityChangeSource;
use App\Enums\Identity\IdentityField;
use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $identity_change_request_id
 * @property IdentityChangeSource $source
 * @property IdentityField $field
 * @property string|null $old_value
 * @property string|null $new_value
 * @property int|null $requested_by_user_id
 * @property int|null $approved_by_user_id
 * @property Carbon $created_at
 * @property-read IdentityChangeRequest|null $request
 * @property-read User|null $requester
 * @property-read User|null $approver
 */
final class UserIdentityChange extends Model
{
    use AppendOnly;

    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'identity_change_request_id', 'field', 'old_value', 'new_value', 'requested_by_user_id', 'approved_by_user_id', 'source'];

    /**
     * @var list<string>
     */
    protected $hidden = ['old_value', 'new_value'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'field' => IdentityField::class,
            'source' => IdentityChangeSource::class,
            'old_value' => 'encrypted',
            'new_value' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<IdentityChangeRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(IdentityChangeRequest::class, 'identity_change_request_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id')->withTrashed();
    }
}
