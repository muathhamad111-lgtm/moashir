<?php

declare(strict_types=1);

namespace App\Models\Notifications;

use App\Casts\LocalizedTextCast;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Notifications\NotificationCategory;
use App\Models\Identity\User;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
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
 * @property AssessmentPath|null $path
 * @property Carbon|null $read_at
 * @property Carbon|null $actioned_at
 * @property Carbon|null $archived_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class Notification extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'code', 'category', 'title', 'body', 'link', 'path'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'category' => NotificationCategory::class,
            'title' => LocalizedTextCast::class,
            'body' => LocalizedTextCast::class,
            'path' => AssessmentPath::class,
            'read_at' => 'datetime',
            'actioned_at' => 'datetime',
            'archived_at' => 'datetime',
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
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ownedBy(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function inInbox(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function unread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    public function isOwnedBy(int $userId): bool
    {
        return $this->user_id === $userId;
    }
}
