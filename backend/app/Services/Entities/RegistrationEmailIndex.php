<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Enums\Identity\BlindIndexPurpose;
use App\Services\Identity\BlindIndex;
use App\Support\ValueObjects\EmailAddress;

final readonly class RegistrationEmailIndex
{
    public function __construct(private BlindIndex $blindIndex) {}

    public function of(EmailAddress $email): string
    {
        return $this->blindIndex->of(BlindIndexPurpose::RegistrationEmail, $email->value);
    }
}
