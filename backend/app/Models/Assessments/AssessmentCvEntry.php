<?php

declare(strict_types=1);

namespace App\Models\Assessments;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $assessment_id
 * @property string $sub_code
 * @property string $body
 * @property int|null $last_edited_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class AssessmentCvEntry extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['assessment_id', 'sub_code', 'body', 'last_edited_by_user_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assessment_id' => 'integer',
            'last_edited_by_user_id' => 'integer',
        ];
    }
}
