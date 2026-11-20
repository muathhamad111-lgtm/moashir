<?php

declare(strict_types=1);

namespace App\Data\Criteria;

use App\Enums\Files\EvidenceFileType;
use App\Support\ValueObjects\LocalizedText;

final readonly class AttachmentRequirementData
{
    /**
     * @param  list<EvidenceFileType>|null  $allowedTypes  null accepts every type the path allows
     */
    public function __construct(
        public ?string $key,
        public LocalizedText $name,
        public bool $isRequired,
        public ?LocalizedText $guidance = null,
        public ?array $allowedTypes = null,
        public ?int $maxFiles = null,
    ) {}

    /**
     * @return list<string>|null
     */
    public function allowedTypeValues(): ?array
    {
        return $this->allowedTypes === null ? null : array_map(static fn (EvidenceFileType $type): string => $type->value, $this->allowedTypes);
    }
}
