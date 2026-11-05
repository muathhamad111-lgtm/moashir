<?php

declare(strict_types=1);

namespace App\Models\Cms;

use App\Casts\LocalizedContentCast;
use App\Models\Files\StoredFile;
use App\Support\ValueObjects\LocalizedContent;
use Database\Factories\Cms\CmsSectionItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $cms_section_id
 * @property bool $is_enabled
 * @property int $sort_order
 * @property LocalizedContent|null $title
 * @property LocalizedContent|null $subtitle
 * @property LocalizedContent|null $description
 * @property LocalizedContent|null $cta_label
 * @property string|null $cta_url
 * @property string|null $icon
 * @property int|null $image_file_id
 * @property array<string, mixed>|null $meta
 * @property int|null $updated_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read CmsSection $section
 * @property-read StoredFile|null $image
 */
final class CmsSectionItem extends Model
{
    /** @use HasFactory<CmsSectionItemFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = ['is_enabled', 'title', 'subtitle', 'description', 'cta_label', 'cta_url', 'icon', 'meta'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
     * @return BelongsTo<CmsSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(CmsSection::class, 'cms_section_id');
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function image(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'image_file_id');
    }
}
