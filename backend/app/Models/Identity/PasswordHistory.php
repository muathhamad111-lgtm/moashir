<?php

declare(strict_types=1);

namespace App\Models\Identity;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $password_hash
 * @property Carbon $created_at
 */
final class PasswordHistory extends Model
{
    public const null UPDATED_AT = null;

    protected $fillable = ['user_id', 'password_hash'];

    protected $hidden = ['password_hash'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
