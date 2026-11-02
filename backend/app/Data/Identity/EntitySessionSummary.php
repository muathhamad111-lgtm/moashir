<?php

declare(strict_types=1);

namespace App\Data\Identity;

use App\Support\ValueObjects\EntityName;

final readonly class EntitySessionSummary
{
    public function __construct(
        public int $id,
        public string $reference,
        public EntityName $name,
        public string $status,
        public bool $profileCompleted,
    ) {}

    /**
     * @return array{id: int, reference: string, name: array{ar: string, en: string|null}, status: string, profile_completed: bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'name' => $this->name->toArray(),
            'status' => $this->status,
            'profile_completed' => $this->profileCompleted,
        ];
    }
}
