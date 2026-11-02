<?php

declare(strict_types=1);

namespace App\Data\Entities;

use App\Contracts\Search\SearchHit;
use App\Enums\Entities\EntityStatus;
use App\Models\Entities\Entity;
use App\Support\ValueObjects\EntityName;

final readonly class EntitySearchHit implements SearchHit
{
    public function __construct(
        public int $id,
        public string $reference,
        public EntityName $name,
        public EntityStatus $status,
    ) {}

    public static function fromEntity(Entity $entity): self
    {
        return new self($entity->id, $entity->reference(), $entity->name, $entity->status);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'name' => $this->name->toArray(),
            'status' => $this->status->value,
        ];
    }
}
