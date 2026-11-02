<?php

declare(strict_types=1);

namespace App\Contracts\Entities;

use App\Models\Entities\Entity;

interface EntityRestoreStep
{
    public function onRestore(Entity $entity): void;
}
