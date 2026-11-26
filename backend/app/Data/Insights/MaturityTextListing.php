<?php

declare(strict_types=1);

namespace App\Data\Insights;

use App\Models\Insights\MaturityInterpretation;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class MaturityTextListing
{
    /**
     * @param  LengthAwarePaginator<int, MaturityInterpretation>  $rows
     * @param  list<string>  $publishedCodes
     */
    public function __construct(public LengthAwarePaginator $rows, public array $publishedCodes) {}
}
