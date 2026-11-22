<?php

declare(strict_types=1);

namespace App\Models\Settings;

use App\Casts\JsonObjectCast;
use App\Data\Settings\BrandColorOverrides;
use App\Data\Settings\BrandColors;
use App\Models\Files\StoredFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $primary_color
 * @property string $secondary_color
 * @property array<string, mixed> $color_overrides
 * @property int|null $logo_light_file_id
 * @property int|null $favicon_file_id
 * @property int|null $site_logo_file_id
 * @property int|null $logo_dark_file_id
 * @property int|null $updated_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read StoredFile|null $logoLight
 * @property-read StoredFile|null $logoDark
 * @property-read StoredFile|null $favicon
 * @property-read StoredFile|null $siteLogo
 */
final class BrandingTheme extends Model
{
    public const int SINGLETON_ID = 1;

    public const string LOCK = 'settings:branding';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [];

    public static function current(): self
    {
        return self::query()->with(['logoLight', 'logoDark', 'favicon', 'siteLogo'])->findOrFail(self::SINGLETON_ID);
    }

    public function colors(): BrandColors
    {
        return new BrandColors($this->primary_color, $this->secondary_color, new BrandColorOverrides($this->color_overrides));
    }

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return ['color_overrides' => JsonObjectCast::class];
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function logoLight(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'logo_light_file_id');
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function logoDark(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'logo_dark_file_id');
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function favicon(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'favicon_file_id');
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function siteLogo(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'site_logo_file_id');
    }
}
