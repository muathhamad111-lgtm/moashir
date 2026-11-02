<?php

declare(strict_types=1);

namespace App\Support\Assessments;

use App\Data\Assessments\SubmittedScores;
use App\Enums\Criteria\ScaleKind;
use App\Models\Assessments\Assessment;

final class ScoreInputRules
{
    private const int MAX_ITEMS = 500;

    /**
     * @return array<string, list<string>>
     */
    public static function for(mixed $assessment, string $presence): array
    {
        $maximum = ($assessment instanceof Assessment ? $assessment->scale_kind : ScaleKind::Relative)->maximum();
        $value = ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:'.$maximum];

        return [
            'scores' => [$presence, 'array:subs,points'],
            'scores.subs' => ['sometimes', 'array', 'max:'.self::MAX_ITEMS],
            'scores.subs.*' => $value,
            'scores.points' => ['sometimes', 'array', 'max:'.self::MAX_ITEMS],
            'scores.points.*' => $value,
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @param  array<array-key, mixed>  $scores
     */
    public static function submitted(array $scores): SubmittedScores
    {
        $subs = $scores['subs'] ?? [];
        $points = $scores['points'] ?? [];

        return new SubmittedScores(is_array($subs) ? $subs : [], is_array($points) ? $points : []);
    }
}
