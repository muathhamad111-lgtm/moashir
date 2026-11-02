<?php

declare(strict_types=1);

namespace App\Contracts\Entities;

use App\Data\Entities\OpenEntityRequest;
use App\Models\Entities\Entity;

interface OpenRequestSource
{
    /**
     * @return list<OpenEntityRequest>
     */
    public function openRequestsOf(Entity $entity): array;
}
