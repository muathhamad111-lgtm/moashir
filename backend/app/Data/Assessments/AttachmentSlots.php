<?php

declare(strict_types=1);

namespace App\Data\Assessments;

final readonly class AttachmentSlots
{
    /**
     * @param  list<AttachmentSlot>  $slots
     */
    public function __construct(public array $slots = []) {}

    public function find(string $key): ?AttachmentSlot
    {
        return array_find($this->slots, static fn (AttachmentSlot $slot): bool => $slot->key === $key);
    }

    /**
     * @return list<string>
     */
    public function requiredKeys(): array
    {
        return array_values(array_map(
            static fn (AttachmentSlot $slot): string => $slot->key,
            array_filter($this->slots, static fn (AttachmentSlot $slot): bool => $slot->isRequired),
        ));
    }
}
