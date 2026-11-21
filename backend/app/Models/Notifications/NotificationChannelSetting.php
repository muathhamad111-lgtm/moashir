<?php

declare(strict_types=1);

namespace App\Models\Notifications;

use App\Enums\Notifications\NotificationChannel;
use App\Enums\Notifications\NotificationCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property NotificationCode $code
 * @property NotificationChannel $channel
 * @property bool $is_enabled
 * @property int|null $updated_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class NotificationChannelSetting extends Model implements Auditable
{
    use AuditableTrait;

    /**
     * @var list<string>
     */
    protected $fillable = ['code', 'channel', 'is_enabled', 'updated_by_user_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['code' => NotificationCode::class, 'channel' => NotificationChannel::class, 'is_enabled' => 'boolean'];
    }
}
