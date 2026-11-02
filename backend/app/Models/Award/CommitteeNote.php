<?php

declare(strict_types=1);

namespace App\Models\Award;

use App\Exceptions\Persistence\AppendOnlyViolation;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $committee_assignment_id
 * @property int|null $sub_criterion_id
 * @property string $body
 * @property int $version
 * @property int $author_user_id
 * @property Carbon|null $superseded_at
 * @property Carbon $created_at
 */
final class CommitteeNote extends Model
{
    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['committee_assignment_id', 'sub_criterion_id', 'body', 'version', 'author_user_id'];

    protected static function booted(): void
    {
        self::updating(static function (self $note): void {
            if ($note->getOriginal('superseded_at') !== null || array_keys($note->getDirty()) !== ['superseded_at']) {
                throw AppendOnlyViolation::forModel(self::class);
            }
        });

        self::deleting(static fn (): never => throw AppendOnlyViolation::forModel(self::class));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'committee_assignment_id' => 'integer',
            'sub_criterion_id' => 'integer',
            'version' => 'integer',
            'author_user_id' => 'integer',
            'superseded_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function current(Builder $query): void
    {
        $query->whereNull('superseded_at');
    }

    public function isGeneral(): bool
    {
        return $this->sub_criterion_id === null;
    }
}
