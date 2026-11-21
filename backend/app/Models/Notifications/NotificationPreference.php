<?php

declare(strict_types=1);

namespace App\Models\Notifications;

use App\Enums\Notifications\NotificationChannel;
use App\Enums\Notifications\NotificationCode;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property NotificationCode $code
 * @property NotificationChannel $channel
 * @property bool $is_enabled
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class NotificationPreference extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'code', 'channel', 'is_enabled'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'code' => NotificationCode::class,
            'channel' => NotificationChannel::class,
            'is_enabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
