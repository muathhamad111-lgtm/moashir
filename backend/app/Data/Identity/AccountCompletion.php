<?php

declare(strict_types=1);

namespace App\Data\Identity;

/**
 * What a signed-in account still has to complete; the SPA asks for it in a skippable modal, never blocking.
 */
final readonly class AccountCompletion
{
    public function __construct(public NationalIdStatus $nationalId, public bool $genderMissing) {}

    /**
     * @return array<string, bool|string|null>
     */
    public function toArray(): array
    {
        return [...$this->nationalId->toArray(), 'gender_missing' => $this->genderMissing];
    }
}
