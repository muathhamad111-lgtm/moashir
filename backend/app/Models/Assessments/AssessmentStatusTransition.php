<?php

declare(strict_types=1);

namespace App\Models\Assessments;

use App\Enums\Assessments\AssessmentVerb;
use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $assessment_id
 * @property string|null $from_status
 * @property string $to_status
 * @property AssessmentVerb $verb
 * @property int|null $actor_user_id
 * @property string|null $reason
 * @property Carbon $created_at
 */
final class AssessmentStatusTransition extends Model
{
    use AppendOnly;

    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['assessment_id', 'from_status', 'to_status', 'verb', 'actor_user_id', 'reason'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assessment_id' => 'integer',
            'verb' => AssessmentVerb::class,
            'actor_user_id' => 'integer',
        ];
    }
}
