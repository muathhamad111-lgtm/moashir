<?php

declare(strict_types=1);

namespace App\Models\Assessments;

use App\Exceptions\Persistence\AppendOnlyViolation;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $assessment_id
 * @property string $body
 * @property int $author_user_id
 * @property int $version
 * @property string|null $change_reason
 * @property Carbon|null $superseded_at
 * @property Carbon $created_at
 */
final class AssessmentSummary extends Model
{
    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['assessment_id', 'body', 'author_user_id', 'version', 'change_reason'];

    /**
     * @var list<string>
     */
    protected $hidden = ['author_user_id', 'change_reason'];

    protected static function booted(): void
    {
        self::updating(static function (self $summary): void {
            if ($summary->getOriginal('superseded_at') !== null || array_keys($summary->getDirty()) !== ['superseded_at']) {
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
            'assessment_id' => 'integer',
            'author_user_id' => 'integer',
            'version' => 'integer',
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
}
