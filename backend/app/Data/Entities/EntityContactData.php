<?php

declare(strict_types=1);

namespace App\Data\Entities;

use App\Support\ValueObjects\PhoneNumber;

final readonly class EntityContactData
{
    public function __construct(
        public string $authorizedPersonName,
        public ?PhoneNumber $phone,
    ) {}
}
