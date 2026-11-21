<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Contracts\Entities\EntityErasureStep;
use App\Models\Entities\Entity;

final readonly class EntityVatCertificateEraser implements EntityErasureStep
{
    public function __construct(private EntityVatCertificateDetacher $certificates) {}

    public function erase(Entity $entity): void
    {
        $this->certificates->detach($entity);
        $entity->save();
    }
}
