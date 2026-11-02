<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\Entities\EntityErasureStep;
use App\Models\Entities\Entity;

final class RecordingErasureStep implements EntityErasureStep
{
    /**
     * @var list<array{id: int, name: string}>
     */
    public array $erased = [];

    public function erase(Entity $entity): void
    {
        $this->erased[] = ['id' => $entity->id, 'name' => $entity->name->ar];
    }
}
