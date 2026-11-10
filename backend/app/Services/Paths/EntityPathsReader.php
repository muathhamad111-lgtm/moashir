<?php

declare(strict_types=1);

namespace App\Services\Paths;

use App\Data\Paths\EntityPaths;
use App\Models\Entities\Entity;

final readonly class EntityPathsReader
{
    public function __construct(
        private SelfPathReader $self,
        private AccreditedPathReader $accredited,
        private AwardPathReader $award,
        private PaymentToSettleReader $payments,
    ) {}

    public function read(Entity $entity): EntityPaths
    {
        return new EntityPaths(
            $this->self->read($entity),
            $this->accredited->read($entity),
            $this->award->read($entity),
            $this->payments->read($entity),
        );
    }
}
