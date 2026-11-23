<?php

declare(strict_types=1);

namespace App\Models\Nda;

use App\Enums\Nda\NdaReminderSource;
use App\Models\Identity\TermsVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $terms_version_id
 * @property NdaReminderSource $source
 * @property int $recipients
 * @property Carbon $created_at
 */
final class NdaReminder extends Model
{
    public const null UPDATED_AT = null;

    protected $fillable = ['terms_version_id', 'source', 'recipients'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['source' => NdaReminderSource::class, 'recipients' => 'integer', 'created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<TermsVersion, $this>
     */
    public function termsVersion(): BelongsTo
    {
        return $this->belongsTo(TermsVersion::class);
    }
}
