<?php

declare(strict_types=1);

namespace App\Enums\Entities;

enum ProfileChangeVerdict: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * The items of a decision that received this verdict.
     *
     * @param  array<string, string>  $decisions
     * @return list<string>
     */
    public function itemsIn(array $decisions): array
    {
        return array_keys(array_filter($decisions, fn (string $decided): bool => $decided === $this->value));
    }
}
