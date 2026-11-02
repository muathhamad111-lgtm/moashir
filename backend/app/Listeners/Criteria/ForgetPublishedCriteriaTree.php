<?php

declare(strict_types=1);

namespace App\Listeners\Criteria;

use App\Events\Criteria\CriteriaEditApplied;
use App\Events\Criteria\CriteriaVersionPublished;
use App\Services\Criteria\PublishedCriteriaTree;

final readonly class ForgetPublishedCriteriaTree
{
    public function __construct(private PublishedCriteriaTree $tree) {}

    public function handle(CriteriaVersionPublished|CriteriaEditApplied $event): void
    {
        $this->tree->forget();
    }
}
