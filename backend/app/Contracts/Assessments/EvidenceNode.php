<?php

declare(strict_types=1);

namespace App\Contracts\Assessments;

use App\Data\Assessments\AttachmentSlot;
use App\Enums\Assessments\EvidenceLevel;
use App\Support\ValueObjects\LocalizedText;

/**
 * A criterion that evidence can be uploaded to: a main criterion or a sub-criterion of the request's version.
 */
interface EvidenceNode
{
    public function level(): EvidenceLevel;

    public function nodeId(): int;

    public function nodeCode(): string;

    public function nodeName(): LocalizedText;

    public function slot(string $key): ?AttachmentSlot;

    /**
     * @return list<string>
     */
    public function requiredSlotKeys(): array;
}
