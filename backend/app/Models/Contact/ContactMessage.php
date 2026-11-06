<?php

declare(strict_types=1);

namespace App\Models\Contact;

use App\Enums\Contact\ContactEnquiryType;
use App\Enums\Contact\ContactEntryKind;
use App\Enums\Contact\ContactMessageStatus;
use App\Enums\Identity\Locale;
use App\Models\Concerns\HasPublicId;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use Database\Factories\Contact\ContactMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property string $reference
 * @property ContactMessageStatus $status
 * @property ContactEnquiryType $enquiry_type
 * @property string $sender_name
 * @property string $sender_email
 * @property string|null $sender_mobile
 * @property string|null $organization_name
 * @property Locale $locale
 * @property int|null $user_id
 * @property int|null $entity_id
 * @property int|null $assigned_to_user_id
 * @property int|null $privacy_terms_version_id
 * @property Carbon $privacy_consent_at
 * @property Carbon $last_activity_at
 * @property Carbon|null $replied_at
 * @property Carbon|null $sender_seen_at
 * @property Carbon|null $closed_at
 * @property int|null $closed_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $sender
 * @property-read Entity|null $entity
 * @property-read User|null $assignee
 * @property-read User|null $closedBy
 * @property-read Collection<int, ContactMessageEntry> $entries
 */
final class ContactMessage extends Model
{
    /** @use HasFactory<ContactMessageFactory> */
    use HasFactory;

    use HasPublicId;

    private const int PREVIEW_LENGTH = 160;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference', 'status', 'enquiry_type', 'sender_name', 'sender_email', 'sender_mobile', 'organization_name', 'locale',
        'user_id', 'entity_id', 'privacy_terms_version_id', 'privacy_consent_at', 'last_activity_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['sender_mobile'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContactMessageStatus::class,
            'enquiry_type' => ContactEnquiryType::class,
            'sender_mobile' => 'encrypted',
            'locale' => Locale::class,
            'user_id' => 'integer',
            'entity_id' => 'integer',
            'assigned_to_user_id' => 'integer',
            'privacy_terms_version_id' => 'integer',
            'privacy_consent_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'replied_at' => 'datetime',
            'sender_seen_at' => 'datetime',
            'closed_at' => 'datetime',
            'closed_by_user_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id')->withTrashed();
    }

    /**
     * @return HasMany<ContactMessageEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(ContactMessageEntry::class)->orderBy('id');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function sentBy(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withUnreadReply(Builder $query): void
    {
        $query->whereNotNull('replied_at')->where(static fn (Builder $unseen): Builder => $unseen
            ->whereNull('sender_seen_at')
            ->orWhereColumn('sender_seen_at', '<', 'replied_at'));
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withSenderPreview(Builder $query): void
    {
        $latestVisibleEntry = ContactMessageEntry::query()
            ->whereColumn('contact_message_id', 'contact_messages.id')
            ->whereIn('kind', ContactEntryKind::senderVisible())
            ->orderByDesc('id')
            ->limit(1);

        $query->addSelect([
            'preview' => (clone $latestVisibleEntry)->selectRaw('left(body, ?)', [self::PREVIEW_LENGTH]),
            'last_entry_kind' => (clone $latestVisibleEntry)->select('kind'),
        ]);
    }

    public function hasUnreadReply(): bool
    {
        return $this->replied_at instanceof Carbon && (! $this->sender_seen_at instanceof Carbon || $this->sender_seen_at->lessThan($this->replied_at));
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function isLinked(): bool
    {
        return $this->user_id !== null;
    }

    public function isSentBy(?int $userId): bool
    {
        return $userId !== null && $this->user_id === $userId;
    }
}
