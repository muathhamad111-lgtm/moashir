<?php

declare(strict_types=1);

namespace App\Data\Entities;

use App\Enums\Entities\EntityRequestPath;

final readonly class OpenEntityRequest
{
    public function __construct(
        public int $id,
        public string $number,
        public EntityRequestPath $path,
        public string $status,
        public ?int $cycleId = null,
        public bool $isFrozen = false,
    ) {}

    public function frozenWith(bool $entityFrozen): self
    {
        return new self($this->id, $this->number, $this->path, $this->status, $this->cycleId, $entityFrozen);
    }

    /**
     * @return array{id: int, number: string, path: string, status: string, cycle_id: int|null, is_frozen: bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'path' => $this->path->value,
            'status' => $this->status,
            'cycle_id' => $this->cycleId,
            'is_frozen' => $this->isFrozen,
        ];
    }
}
