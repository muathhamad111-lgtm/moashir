<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Contracts\Assessments\EvidenceNode;
use App\Enums\Assessments\EvidenceLevel;
use App\Support\ValueObjects\LocalizedText;

/**
 * An active main criterion of the request's version, with the attachments it asks for itself.
 */
final readonly class BoundMain implements EvidenceNode
{
    public function __construct(public int $id, public string $code, public LocalizedText $name, public AttachmentSlots $slots) {}

    public function level(): EvidenceLevel
    {
        return EvidenceLevel::Main;
    }

    public function nodeId(): int
    {
        return $this->id;
    }

    public function nodeCode(): string
    {
        return $this->code;
    }

    public function nodeName(): LocalizedText
    {
        return $this->name;
    }

    public function slot(string $key): ?AttachmentSlot
    {
        return $this->slots->find($key);
    }

    /**
     * @return list<string>
     */
    public function requiredSlotKeys(): array
    {
        return $this->slots->requiredKeys();
    }
}
