<?php

declare(strict_types=1);

namespace App\Models\Notifications;

use App\Casts\LocalizedTextCast;
use App\Enums\Notifications\NotificationCategory;
use App\Models\Identity\User;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string $code
 * @property NotificationCategory $category
 * @property LocalizedText $title
 * @property LocalizedText $body
 * @property string|null $link
 * @property Carbon|null $read_at
 */
final class Notification extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'code', 'category', 'title', 'body', 'link'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => NotificationCategory::class,
            'title' => LocalizedTextCast::class,
            'body' => LocalizedTextCast::class,
            'read_at' => 'datetime',
            'actioned_at' => 'datetime',
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
