<?php

declare(strict_types=1);

namespace App\Data\Identity;

use App\Support\ValueObjects\NationalId;

final readonly class IdentityChangeDraft
{
    public function __construct(
        public ?string $newName,
        public ?NationalId $newNationalId,
        public string $reason,
    ) {}
}
