<?php

declare(strict_types=1);

namespace App\Data\Integrations;

use App\Enums\Integrations\SmsPurpose;
use App\Support\ValueObjects\PhoneNumber;

final readonly class SmsMessage
{
    public function __construct(
        public PhoneNumber $recipient,
        public string $text,
        public SmsPurpose $purpose,
    ) {}

    public function internationalNumber(): string
    {
        return ltrim($this->recipient->value, '+');
    }
}
