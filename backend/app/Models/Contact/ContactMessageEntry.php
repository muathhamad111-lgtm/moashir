<?php

declare(strict_types=1);

namespace App\Models\Contact;

use App\Enums\Contact\ContactEntryKind;
use App\Exceptions\Persistence\AppendOnlyViolation;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $contact_message_id
 * @property ContactEntryKind $kind
 * @property string $body
 * @property int|null $author_user_id
 * @property Carbon $created_at
 * @property-read ContactMessage $message
 * @property-read User|null $author
 */
final class ContactMessageEntry extends Model
{
    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['kind', 'body', 'author_user_id'];

    protected static function booted(): void
    {
        self::updating(static fn (): never => throw AppendOnlyViolation::forModel(self::class));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contact_message_id' => 'integer',
            'kind' => ContactEntryKind::class,
            'author_user_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ContactMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(ContactMessage::class, 'contact_message_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id')->withTrashed();
    }
}
