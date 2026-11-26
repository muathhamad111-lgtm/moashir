<?php

declare(strict_types=1);

namespace App\Data\Settings;

use App\Support\ValueObjects\VatRegistrationNumber;

final readonly class VatRegistrationNumberEdit
{
    public function __construct(public ?VatRegistrationNumber $number) {}
}
