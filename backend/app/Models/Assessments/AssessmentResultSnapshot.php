<?php

declare(strict_types=1);

namespace App\Models\Assessments;

use App\Casts\EarnedScoreCast;
use App\Casts\PercentCast;
use App\Casts\WeightCast;
use App\Enums\Assessments\ResultSnapshotKind;
use App\Enums\Criteria\ScaleKind;
use App\Models\Concerns\AppendOnly;
use App\Support\ValueObjects\EarnedScore;
use App\Support\ValueObjects\Percent;
use App\Support\ValueObjects\Weight;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $assessment_id
 * @property int $version
 * @property ResultSnapshotKind $kind
 * @property int $criteria_version_id
 * @property ScaleKind $scale_kind
 * @property EarnedScore $total_score
 * @property Weight $total_weight
 * @property Percent $percent
 * @property array<string, mixed> $breakdown
 * @property int|null $computed_by_user_id
 * @property string|null $reason
 * @property Carbon $created_at
 */
final class AssessmentResultSnapshot extends Model
{
    use AppendOnly;

    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'assessment_id', 'version', 'kind', 'criteria_version_id', 'scale_kind', 'total_score', 'total_weight',
        'percent', 'breakdown', 'computed_by_user_id', 'reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assessment_id' => 'integer',
            'version' => 'integer',
            'kind' => ResultSnapshotKind::class,
            'criteria_version_id' => 'integer',
            'scale_kind' => ScaleKind::class,
            'total_score' => EarnedScoreCast::class,
            'total_weight' => WeightCast::class,
            'percent' => PercentCast::class,
            'breakdown' => 'array',
            'computed_by_user_id' => 'integer',
        ];
    }
}
