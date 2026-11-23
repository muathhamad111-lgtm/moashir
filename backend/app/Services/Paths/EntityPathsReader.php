<?php

declare(strict_types=1);

namespace App\Services\Paths;

use App\Data\Paths\EntityPaths;
use App\Models\Entities\Entity;
use App\Services\Award\OpenCycleOptionsReader;

final readonly class EntityPathsReader
{
    public function __construct(
        private SelfPathReader $self,
        private AccreditedPathReader $accredited,
        private AwardPathReader $award,
        private PaymentToSettleReader $payments,
        private OpenCycleOptionsReader $openCycles,
    ) {}

    public function read(Entity $entity): EntityPaths
    {
        $self = $this->self->read($entity);
        $accredited = $this->accredited->read($entity);
        $award = $this->award->read($entity);

        return new EntityPaths($self, $accredited, $award, $this->payments->read($entity, $self, $accredited, $award), $this->openCycles->forEntity($entity));
    }
}
