<?php

declare(strict_types=1);

namespace App\Models\Award;

use App\Casts\PercentCast;
use App\Enums\Assessments\ScoreLevel;
use App\Enums\Criteria\ScaleKind;
use App\Exceptions\Persistence\AppendOnlyViolation;
use App\Support\ValueObjects\Percent;
use App\Support\ValueObjects\RawScore;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $committee_assignment_id
 * @property ScoreLevel $level
 * @property int|null $sub_criterion_id
 * @property int|null $guiding_point_id
 * @property int $scorer_user_id
 * @property string $raw_value
 * @property Percent $percent
 * @property int $version
 * @property string|null $change_reason
 * @property Carbon|null $superseded_at
 * @property Carbon $created_at
 */
final class CommitteeScore extends Model
{
    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'committee_assignment_id', 'level', 'sub_criterion_id', 'guiding_point_id', 'scorer_user_id', 'raw_value', 'percent',
        'version', 'change_reason',
    ];

    protected static function booted(): void
    {
        self::updating(static function (self $score): void {
            if ($score->getOriginal('superseded_at') !== null || array_keys($score->getDirty()) !== ['superseded_at']) {
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
            'level' => ScoreLevel::class,
            'sub_criterion_id' => 'integer',
            'guiding_point_id' => 'integer',
            'scorer_user_id' => 'integer',
            'percent' => PercentCast::class,
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

    public function nodeId(): int
    {
        return (int) ($this->level === ScoreLevel::Sub ? $this->sub_criterion_id : $this->guiding_point_id);
    }

    public function rawScore(ScaleKind $scale): RawScore
    {
        return RawScore::on($scale, $this->raw_value);
    }
}
