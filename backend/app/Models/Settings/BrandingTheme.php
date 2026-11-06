<?php

declare(strict_types=1);

namespace App\Models\Settings;

use App\Enums\Settings\AccentToken;
use App\Models\Files\StoredFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property AccentToken $accent_token
 * @property int|null $logo_light_file_id
 * @property int|null $logo_dark_file_id
 * @property int|null $updated_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read StoredFile|null $logoLight
 * @property-read StoredFile|null $logoDark
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
        return self::query()->with(['logoLight', 'logoDark'])->findOrFail(self::SINGLETON_ID);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accent_token' => AccentToken::class,
        ];
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
}
