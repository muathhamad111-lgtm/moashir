<?php

declare(strict_types=1);

namespace App\Models\Cms;

use App\Casts\JsonObjectCast;
use App\Casts\LocalizedContentCast;
use App\Enums\Cms\CmsPageKey;
use App\Models\Identity\User;
use App\Support\ValueObjects\LocalizedContent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property CmsPageKey $key
 * @property LocalizedContent|null $title
 * @property array<string, mixed> $seo
 * @property bool $is_published
 * @property array<string, mixed>|null $published_content
 * @property Carbon|null $published_at
 * @property int|null $published_by_user_id
 * @property Carbon|null $content_updated_at
 * @property bool $has_draft_changes
 * @property int|null $updated_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, CmsSection> $sections
 * @property-read User|null $publishedBy
 */
final class CmsPage extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['title', 'seo'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => CmsPageKey::class,
            'title' => LocalizedContentCast::class,
            'seo' => JsonObjectCast::class,
            'is_published' => 'boolean',
            'published_content' => 'array',
            'published_at' => 'datetime',
            'content_updated_at' => 'datetime',
            'has_draft_changes' => 'boolean',
        ];
    }

    /**
     * @return HasMany<CmsSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(CmsSection::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id')->withTrashed();
    }

    public function hasUnpublishedChanges(): bool
    {
        return ! $this->published_at instanceof Carbon || $this->has_draft_changes;
    }
}
