<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Contracts\Assessments\EvidenceNode;
use App\Enums\Files\EvidenceFileType;

/**
 * Where an accepted upload goes: the criterion, its slot (null = the sub's additional evidence) and the file types
 * the slot takes within the path's settings.
 */
final readonly class EvidenceDestination
{
    /**
     * @param  list<EvidenceFileType>  $types
     */
    public function __construct(public EvidenceNode $node, public ?AttachmentSlot $slot, public array $types) {}
}
