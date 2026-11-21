<?php

declare(strict_types=1);

namespace App\Support\Assessments;

use App\Data\Assessments\SubmittedScores;
use App\Enums\Criteria\ScoringType;

final class ScoreInputRules
{
    private const int MAX_ITEMS = 500;

    /**
     * The widest scoring type; the parser then checks each value against its own unit's type.
     */
    private const ScoringType CEILING = ScoringType::Relative;

    /**
     * @return array<string, list<string>>
     */
    public static function for(string $presence): array
    {
        $value = ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:'.self::CEILING->maximum()];

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
     * A Super Admin override names a value for every unit it lists: an override never clears a score.
     *
     * @return array<string, list<string>>
     */
    public static function forOverride(): array
    {
        $rules = self::for('required');
        unset($rules['reason']);

        foreach (['scores.subs.*', 'scores.points.*'] as $values) {
            $rules[$values] = ['required', ...array_values(array_diff($rules[$values], ['nullable']))];
        }

        return $rules;
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
