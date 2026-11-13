<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Support\ValueObjects\LocalizedText;

final readonly class BoundSub
{
    /**
     * @param  list<AttachmentSlot>  $slots
     */
    public function __construct(public int $id, public string $code, public LocalizedText $name, public bool $hasCv, public array $slots) {}

    public function hasSlot(string $key): bool
    {
        return array_any($this->slots, static fn (AttachmentSlot $slot): bool => $slot->key === $key);
    }

    /**
     * @return list<string>
     */
    public function requiredSlotKeys(): array
    {
        return array_values(array_map(
            static fn (AttachmentSlot $slot): string => $slot->key,
            array_filter($this->slots, static fn (AttachmentSlot $slot): bool => $slot->isRequired),
        ));
    }
}
