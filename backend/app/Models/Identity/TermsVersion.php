<?php

declare(strict_types=1);

namespace App\Models\Identity;

use App\Casts\LocalizedTextCast;
use App\Enums\Identity\TermsKind;
use App\Enums\Identity\TermsVersionStatus;
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
 * @property LocalizedText|null $title
 * @property LocalizedText $body
 * @property Carbon|null $effective_from
 * @property bool $is_current
 * @property Carbon|null $published_at
 * @property int|null $published_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class TermsVersion extends Model
{
    protected $fillable = ['kind', 'version', 'title', 'body', 'effective_from', 'is_current', 'published_by_user_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'title' => LocalizedTextCast::class,
            'body' => LocalizedTextCast::class,
            'effective_from' => 'date',
            'is_current' => 'boolean',
            'published_at' => 'datetime',
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
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ofKind(Builder $query, TermsKind $kind): void
    {
        $query->where('kind', $kind->value);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function drafts(Builder $query): void
    {
        $query->whereNull('published_at');
    }

    /**
     * @return HasMany<TermsAcceptance, $this>
     */
    public function acceptances(): HasMany
    {
        return $this->hasMany(TermsAcceptance::class);
    }

    public function termsKind(): TermsKind
    {
        return TermsKind::from($this->kind);
    }

    public function isDraft(): bool
    {
        return ! $this->published_at instanceof Carbon;
    }

    public function status(): TermsVersionStatus
    {
        return match (true) {
            $this->isDraft() => TermsVersionStatus::Draft,
            $this->is_current => TermsVersionStatus::Current,
            default => TermsVersionStatus::Superseded,
        };
    }
}
