<?php

declare(strict_types=1);

namespace App\Contracts\Entities;

use App\Data\Entities\FreezableDeadline;
use App\Models\Entities\Entity;

interface FreezableDeadlineSource
{
    /**
     * @return list<FreezableDeadline>
     */
    public function runningDeadlinesOf(Entity $entity): array;
}
