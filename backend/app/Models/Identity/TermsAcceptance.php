<?php

declare(strict_types=1);

namespace App\Models\Identity;

use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $terms_version_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 */
final class TermsAcceptance extends Model
{
    use AppendOnly;

    public const null UPDATED_AT = null;

    protected $fillable = ['user_id', 'terms_version_id', 'ip_address', 'user_agent'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<TermsVersion, $this>
     */
    public function termsVersion(): BelongsTo
    {
        return $this->belongsTo(TermsVersion::class);
    }
}
