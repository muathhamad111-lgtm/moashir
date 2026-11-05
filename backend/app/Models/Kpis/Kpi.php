<?php

declare(strict_types=1);

namespace App\Models\Kpis;

use App\Casts\KpiNumberCast;
use App\Casts\LocalizedContentCast;
use App\Enums\Kpis\KpiColorToken;
use App\Enums\Kpis\KpiStatus;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Support\ValueObjects\KpiNumber;
use App\Support\ValueObjects\LocalizedContent;
use Database\Factories\Kpis\KpiFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property LocalizedContent $name
 * @property int|null $kpi_category_id
 * @property int|null $kpi_type_id
 * @property KpiNumber|null $value
 * @property KpiNumber|null $target
 * @property KpiNumber|null $baseline
 * @property LocalizedContent|null $short_description
 * @property LocalizedContent|null $full_description
 * @property LocalizedContent|null $unit
 * @property LocalizedContent|null $owner
 * @property LocalizedContent|null $data_source
 * @property LocalizedContent|null $calc_method
 * @property LocalizedContent|null $notes
 * @property int|null $measurement_year
 * @property KpiStatus|null $status
 * @property string|null $external_link
 * @property string|null $icon
 * @property int|null $image_file_id
 * @property KpiColorToken|null $card_color_token
 * @property KpiColorToken|null $badge_color_token
 * @property bool $show_on_homepage
 * @property bool $is_published
 * @property Carbon|null $published_at
 * @property int $sort_order
 * @property Carbon|null $value_updated_at
 * @property int|null $updated_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read KpiCategory|null $category
 * @property-read KpiType|null $type
 * @property-read StoredFile|null $image
 * @property-read User|null $updatedBy
 * @property-read Collection<int, KpiValue> $values
 */
final class Kpi extends Model
{
    /** @use HasFactory<KpiFactory> */
    use HasFactory;

    use SoftDeletes;

    public const string ORDER_LOCK = 'kpis:order';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'slug', 'name', 'kpi_category_id', 'kpi_type_id', 'value', 'target', 'baseline', 'short_description',
        'full_description', 'unit', 'owner', 'data_source', 'calc_method', 'notes', 'measurement_year', 'status',
        'external_link', 'icon', 'card_color_token', 'badge_color_token', 'show_on_homepage',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => LocalizedContentCast::class,
            'value' => KpiNumberCast::class,
            'target' => KpiNumberCast::class,
            'baseline' => KpiNumberCast::class,
            'short_description' => LocalizedContentCast::class,
            'full_description' => LocalizedContentCast::class,
            'unit' => LocalizedContentCast::class,
            'owner' => LocalizedContentCast::class,
            'data_source' => LocalizedContentCast::class,
            'calc_method' => LocalizedContentCast::class,
            'notes' => LocalizedContentCast::class,
            'measurement_year' => 'integer',
            'status' => KpiStatus::class,
            'card_color_token' => KpiColorToken::class,
            'badge_color_token' => KpiColorToken::class,
            'show_on_homepage' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'sort_order' => 'integer',
            'value_updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<KpiCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(KpiCategory::class, 'kpi_category_id')->withTrashed();
    }

    /**
     * @return BelongsTo<KpiType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(KpiType::class, 'kpi_type_id');
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function image(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'image_file_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id')->withTrashed();
    }

    /**
     * @return HasMany<KpiValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(KpiValue::class)->orderBy('measured_on');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }
}
