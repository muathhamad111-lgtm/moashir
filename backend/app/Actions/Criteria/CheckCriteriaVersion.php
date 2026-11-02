<?php

declare(strict_types=1);

namespace App\Actions\Criteria;

use App\Data\Criteria\ConsistencyReport;
use App\Models\Criteria\CriteriaVersion;
use App\Services\Criteria\CriteriaConsistencyChecker;
use App\Services\Criteria\CriteriaTreeLoader;

final readonly class CheckCriteriaVersion
{
    public function __construct(private CriteriaTreeLoader $loader, private CriteriaConsistencyChecker $checker) {}

    public function handle(CriteriaVersion $version): ConsistencyReport
    {
        return $this->checker->check($this->loader->load($version));
    }
}
