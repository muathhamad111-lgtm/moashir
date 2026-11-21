<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Contracts\Assessments\EvidenceNode;

final readonly class SubmissionCoverage
{
    /**
     * @param  array<string, string>  $cvBodies
     * @param  array<string, true>  $providedSlots
     */
    public function __construct(private array $cvBodies, private array $providedSlots) {}

    public function lacksCv(BoundSub $sub): bool
    {
        return $sub->hasCv && trim($this->cvBodies[$sub->code] ?? '') === '';
    }

    /**
     * @return list<string>
     */
    public function missingSlotKeysOf(EvidenceNode $node): array
    {
        return array_values(array_filter(
            $node->requiredSlotKeys(),
            fn (string $key): bool => ! isset($this->providedSlots[$node->nodeCode().'|'.$key]),
        ));
    }

    public function covers(BoundSub $sub): bool
    {
        return ! $this->lacksCv($sub) && $this->missingSlotKeysOf($sub) === [];
    }
}
