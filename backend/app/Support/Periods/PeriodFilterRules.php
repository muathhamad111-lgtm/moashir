<?php

declare(strict_types=1);

namespace App\Support\Periods;

use Illuminate\Validation\Rule;

final class PeriodFilterRules
{
    /**
     * @return list<mixed>
     */
    public static function for(mixed $value): array
    {
        return [
            'nullable',
            'string',
            'regex:/^(none|[1-9][0-9]{0,9})$/',
            Rule::when(is_string($value) && ctype_digit($value), [Rule::exists('assessment_periods', 'id')]),
        ];
    }
}
