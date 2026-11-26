<?php

declare(strict_types=1);

namespace App\Models\Identity;

use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $terms_version_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property list<string>|null $accepted_roles
 * @property Carbon $created_at
 */
final class TermsAcceptance extends Model
{
    use AppendOnly;

    public const null UPDATED_AT = null;

    protected $fillable = ['user_id', 'terms_version_id', 'ip_address', 'user_agent', 'accepted_roles'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['accepted_roles' => 'array', 'created_at' => 'datetime'];
    }

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
