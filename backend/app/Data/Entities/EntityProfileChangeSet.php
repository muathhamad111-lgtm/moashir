<?php

declare(strict_types=1);

namespace App\Data\Entities;

use App\Enums\Entities\EntityProfileField;

final readonly class EntityProfileChangeSet
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function __construct(
        public array $before,
        public array $after,
    ) {}

    public function isEmpty(): bool
    {
        return $this->after === [];
    }

    /**
     * @return list<EntityProfileField>
     */
    public function fields(): array
    {
        return array_values(array_filter(array_map(EntityProfileField::tryFrom(...), array_keys($this->after))));
    }
}
