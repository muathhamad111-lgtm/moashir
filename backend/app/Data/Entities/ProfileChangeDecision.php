<?php

declare(strict_types=1);

namespace App\Data\Entities;

use App\Enums\Entities\ProfileChangeVerdict;

/**
 * A reviewer's decision on a profile change request: the items approved (fields and files) out of the revision they
 * reviewed; every other item is rejected for the given reason.
 */
final readonly class ProfileChangeDecision
{
    /**
     * @param  list<string>  $approvedItems
     */
    public function __construct(
        public int $revision,
        public array $approvedItems,
        public ?string $reason,
    ) {}

    public function approves(string $item): bool
    {
        return in_array($item, $this->approvedItems, true);
    }

    /**
     * @param  list<string>  $items
     */
    public function rejectsAnyOf(array $items): bool
    {
        return array_diff($items, $this->approvedItems) !== [];
    }

    /**
     * @param  list<string>  $items
     * @return array<string, string>
     */
    public function verdictsFor(array $items): array
    {
        $verdicts = [];

        foreach ($items as $item) {
            $verdicts[$item] = ($this->approves($item) ? ProfileChangeVerdict::Approved : ProfileChangeVerdict::Rejected)->value;
        }

        return $verdicts;
    }
}
