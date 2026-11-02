<?php

declare(strict_types=1);

namespace App\Models\Identity;

use App\Casts\LocalizedTextCast;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $kind
 * @property string $version
 * @property LocalizedText $body
 * @property Carbon $effective_from
 * @property bool $is_current
 * @property int $published_by_user_id
 */
final class TermsVersion extends Model
{
    protected $fillable = ['kind', 'version', 'body', 'effective_from', 'is_current', 'published_by_user_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'body' => LocalizedTextCast::class,
            'effective_from' => 'date',
            'is_current' => 'boolean',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function current(Builder $query): void
    {
        $query->where('is_current', true);
    }

    /**
     * @return HasMany<TermsAcceptance, $this>
     */
    public function acceptances(): HasMany
    {
        return $this->hasMany(TermsAcceptance::class);
    }
}
