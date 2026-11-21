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
        public array $before = [],
        public array $after = [],
    ) {}

    public function merge(self $other): self
    {
        return new self([...$this->before, ...$other->before], [...$this->after, ...$other->after]);
    }

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

    public function touchesEntityRecord(): bool
    {
        return array_any($this->fields(), static fn (EntityProfileField $field): bool => $field->isEntityRecord());
    }

    public function withoutPersonalData(): self
    {
        $personal = array_flip(array_map(
            static fn (EntityProfileField $field): string => $field->value,
            array_values(array_filter($this->fields(), static fn (EntityProfileField $field): bool => $field->holdsPersonalData())),
        ));

        return new self(array_diff_key($this->before, $personal), array_diff_key($this->after, $personal));
    }
}
