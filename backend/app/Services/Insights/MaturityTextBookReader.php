<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Data\Insights\MaturityTextBook;
use App\Models\Insights\MaturityInterpretation;
use App\Support\ValueObjects\LocalizedText;

final class MaturityTextBookReader
{
    public function read(): MaturityTextBook
    {
        $generic = [];
        $overrides = [];

        foreach (MaturityInterpretation::query()->whereNotNull('text')->get() as $row) {
            if (! $row->text instanceof LocalizedText) {
                continue;
            }

            if ($row->main_criterion_code === null) {
                $generic[$row->band->value] = $row->text;
            } else {
                $overrides[$row->band->value][$row->main_criterion_code] = $row->text;
            }
        }

        return new MaturityTextBook($generic, $overrides);
    }
}
