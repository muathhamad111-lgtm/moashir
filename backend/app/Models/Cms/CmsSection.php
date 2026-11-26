<?php

declare(strict_types=1);

namespace App\Models\Cms;

use App\Casts\LocalizedContentCast;
use App\Enums\Cms\CmsSectionType;
use App\Models\Files\StoredFile;
use App\Support\ValueObjects\LocalizedContent;
use Database\Factories\Cms\CmsSectionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $cms_page_id
 * @property string $key
 * @property CmsSectionType $type
 * @property bool $is_enabled
 * @property int $sort_order
 * @property LocalizedContent|null $title
 * @property LocalizedContent|null $subtitle
 * @property LocalizedContent|null $description
 * @property LocalizedContent|null $cta_label
 * @property string|null $cta_url
 * @property string|null $icon
 * @property int|null $image_file_id
 * @property string|null $background
 * @property array<string, mixed>|null $meta
 * @property int|null $updated_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read CmsPage $page
 * @property-read Collection<int, CmsSectionItem> $items
 * @property-read StoredFile|null $image
 */
final class CmsSection extends Model
{
    /** @use HasFactory<CmsSectionFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = ['key', 'type', 'is_enabled', 'title', 'subtitle', 'description', 'cta_label', 'cta_url', 'icon', 'background', 'meta'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CmsSectionType::class,
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
            'title' => LocalizedContentCast::class,
            'subtitle' => LocalizedContentCast::class,
            'description' => LocalizedContentCast::class,
            'cta_label' => LocalizedContentCast::class,
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<CmsPage, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(CmsPage::class, 'cms_page_id');
    }

    /**
     * @return HasMany<CmsSectionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CmsSectionItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function image(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'image_file_id');
    }
}
