<?php

declare(strict_types=1);

namespace App\Models\Identity;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property int|null $user_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property int $last_activity
 */
final class UserSession extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'sessions';

    protected $keyType = 'string';

    protected $hidden = ['payload'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['last_activity' => 'integer'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function publicId(): string
    {
        return hash_hmac('sha256', $this->id, config()->string('app.key'));
    }
}
