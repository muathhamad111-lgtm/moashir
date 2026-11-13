<?php

declare(strict_types=1);

namespace App\Support\Insights;

use App\Models\Insights\MaturityInterpretation;

final class MaturityTextPresenter
{
    /**
     * @param  list<string>  $publishedCodes
     * @return array<string, mixed>
     */
    public static function present(MaturityInterpretation $row, array $publishedCodes): array
    {
        return [
            'id' => $row->id,
            'band' => $row->band->value,
            'main_criterion_code' => $row->main_criterion_code,
            'is_generic' => $row->isGeneric(),
            'in_published_version' => $row->isGeneric() ? null : in_array($row->main_criterion_code, $publishedCodes, true),
            'text' => $row->text?->toArray(),
            'updated_at' => $row->updated_at->toIso8601String(),
        ];
    }
}
