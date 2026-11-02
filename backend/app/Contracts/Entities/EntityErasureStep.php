<?php

declare(strict_types=1);

namespace App\Contracts\Entities;

use App\Models\Entities\Entity;

interface EntityErasureStep
{
    public function erase(Entity $entity): void;
}
