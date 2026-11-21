<?php

declare(strict_types=1);

namespace App\Actions\Insights;

use App\Data\Insights\MaturityTextListing;
use App\Models\Insights\MaturityInterpretation;
use App\Services\Insights\PublishedMainCriterionCodes;

final readonly class ListMaturityTexts
{
    public function __construct(private PublishedMainCriterionCodes $codes) {}

    public function handle(int $perPage): MaturityTextListing
    {
        return new MaturityTextListing(MaturityInterpretation::query()->ordered()->paginate($perPage), $this->codes->all());
    }
}
