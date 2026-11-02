<?php

declare(strict_types=1);

namespace App\Data\Criteria;

use App\Enums\Criteria\EvaluationType;
use App\Support\ValueObjects\LocalizedText;
use App\Support\ValueObjects\Weight;

final readonly class SubCriterionAttributes
{
    /**
     * @param  list<AttachmentRequirementData>|null  $attachments
     */
    public function __construct(
        public LocalizedText $name,
        public ?LocalizedText $description,
        public ?Weight $weight,
        public bool $distributeEqually,
        public EvaluationType $evaluationType,
        public bool $hasCv,
        public ?LocalizedText $assessorNoteLabel,
        public ?array $attachments,
    ) {}

    public function withWeight(Weight $weight): self
    {
        return new self($this->name, $this->description, $weight, $this->distributeEqually, $this->evaluationType, $this->hasCv, $this->assessorNoteLabel, $this->attachments);
    }
}
