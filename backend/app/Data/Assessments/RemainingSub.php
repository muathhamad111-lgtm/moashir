<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Support\ValueObjects\LocalizedText;

final readonly class RemainingSub
{
    public function __construct(
        public int $subCriterionId,
        public string $code,
        public LocalizedText $name,
        public bool $cvMissing,
        public int $attachmentsRequired,
        public int $attachmentsUploaded,
    ) {}

    public static function of(BoundSub $sub, SubmissionCoverage $coverage): self
    {
        $required = count($sub->requiredSlotKeys());

        return new self($sub->id, $sub->code, $sub->name, $coverage->lacksCv($sub), $required, $required - count($coverage->missingSlotKeysOf($sub)));
    }

    /**
     * @return array{sub_criterion_id: int, code: string, name: array{ar: string, en: string}, cv_missing: bool, attachments_required: int, attachments_uploaded: int}
     */
    public function toArray(): array
    {
        return [
            'sub_criterion_id' => $this->subCriterionId,
            'code' => $this->code,
            'name' => $this->name->toArray(),
            'cv_missing' => $this->cvMissing,
            'attachments_required' => $this->attachmentsRequired,
            'attachments_uploaded' => $this->attachmentsUploaded,
        ];
    }
}
