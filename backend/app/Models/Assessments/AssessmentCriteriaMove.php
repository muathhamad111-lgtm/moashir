<?php

declare(strict_types=1);

namespace App\Models\Assessments;

use App\Enums\Assessments\RebindTrigger;
use App\Enums\Criteria\CriteriaEditScope;
use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $assessment_id
 * @property int $from_version_id
 * @property int $to_version_id
 * @property CriteriaEditScope $scope
 * @property RebindTrigger $triggered_by
 * @property list<array{level: string, code: string}> $kept
 * @property list<array{level: string, code: string}> $reset
 * @property list<array{level: string, code: string}> $added
 * @property Carbon $created_at
 */
final class AssessmentCriteriaMove extends Model
{
    use AppendOnly;

    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['assessment_id', 'from_version_id', 'to_version_id', 'scope', 'triggered_by', 'kept', 'reset', 'added'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assessment_id' => 'integer',
            'from_version_id' => 'integer',
            'to_version_id' => 'integer',
            'scope' => CriteriaEditScope::class,
            'triggered_by' => RebindTrigger::class,
            'kept' => 'array',
            'reset' => 'array',
            'added' => 'array',
        ];
    }
}
