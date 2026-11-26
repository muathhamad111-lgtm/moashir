<?php

declare(strict_types=1);

namespace App\Data\Identity;

use App\Enums\Identity\IdentityChangeKind;
use App\Support\ValueObjects\NationalId;

final readonly class IdentityChangeDraft
{
    public function __construct(
        public ?string $newName,
        public ?NationalId $newNationalId,
        public ?string $reason,
        public IdentityChangeKind $kind = IdentityChangeKind::Correction,
    ) {}

    public static function firstEntry(NationalId $nationalId): self
    {
        return new self(null, $nationalId, null, IdentityChangeKind::FirstEntry);
    }
}
