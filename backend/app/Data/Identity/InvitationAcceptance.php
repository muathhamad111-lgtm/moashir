<?php

declare(strict_types=1);

namespace App\Data\Identity;

use App\Models\Identity\TermsVersion;
use App\Support\ValueObjects\NationalId;

final readonly class InvitationAcceptance
{
    public function __construct(
        public string $password,
        public ?TermsVersion $terms,
        public ?NationalId $nationalId,
    ) {}
}
