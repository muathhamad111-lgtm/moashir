<?php

declare(strict_types=1);

namespace App\Models\Award;

use App\Casts\PercentCast;
use App\Enums\Award\RankStage;
use App\Models\Assessments\Assessment;
use App\Support\ValueObjects\Percent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $award_cycle_id
 * @property int $assessment_id
 * @property int $region_id
 * @property int $committee_average_id
 * @property Percent $average_percent
 * @property int|null $regional_rank
 * @property int|null $national_rank
 * @property int|null $ranked_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Assessment $assessment
 */
final class AwardRanking extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['award_cycle_id', 'assessment_id', 'region_id', 'committee_average_id', 'average_percent'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'award_cycle_id' => 'integer',
            'assessment_id' => 'integer',
            'region_id' => 'integer',
            'committee_average_id' => 'integer',
            'average_percent' => PercentCast::class,
            'regional_rank' => 'integer',
            'national_rank' => 'integer',
            'ranked_by_user_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Assessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function rankAt(RankStage $stage): ?int
    {
        return $stage === RankStage::Regional ? $this->regional_rank : $this->national_rank;
    }
}
