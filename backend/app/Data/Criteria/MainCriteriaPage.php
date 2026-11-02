<?php

declare(strict_types=1);

namespace App\Data\Criteria;

use App\Models\Criteria\CriteriaVersion;
use App\Models\Criteria\MainCriterion;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class MainCriteriaPage
{
    /**
     * @param  LengthAwarePaginator<int, MainCriterion>  $mains
     */
    public function __construct(public ?CriteriaVersion $version, public LengthAwarePaginator $mains) {}
}
