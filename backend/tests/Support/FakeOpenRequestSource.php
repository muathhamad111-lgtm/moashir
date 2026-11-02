<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\Entities\OpenRequestSource;
use App\Data\Entities\OpenEntityRequest;
use App\Models\Entities\Entity;

final class FakeOpenRequestSource implements OpenRequestSource
{
    /**
     * @var array<int, list<OpenEntityRequest>>
     */
    private array $requests = [];

    /**
     * @param  list<OpenEntityRequest>  $requests
     */
    public function give(Entity $entity, array $requests): void
    {
        $this->requests[$entity->id] = $requests;
    }

    public function openRequestsOf(Entity $entity): array
    {
        return $this->requests[$entity->id] ?? [];
    }
}
