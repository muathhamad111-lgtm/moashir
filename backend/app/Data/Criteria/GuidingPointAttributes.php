<?php

declare(strict_types=1);

namespace App\Data\Criteria;

use App\Enums\Criteria\ScoringType;
use App\Support\ValueObjects\LocalizedText;
use App\Support\ValueObjects\Weight;

final readonly class GuidingPointAttributes
{
    /**
     * @param  list<AttachmentRequirementData>|null  $attachments
     */
    public function __construct(
        public LocalizedText $description,
        public ?Weight $weight,
        public ?array $attachments,
        public ?ScoringType $scoringType = null,
    ) {}

    /**
     * @param  Weight|null  $weight  null for an indicator of a directly evaluated sub-criterion
     */
    public function withWeight(?Weight $weight): self
    {
        return new self($this->description, $weight, $this->attachments, $this->scoringType);
    }
}
