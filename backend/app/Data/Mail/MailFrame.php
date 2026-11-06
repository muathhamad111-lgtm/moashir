<?php

declare(strict_types=1);

namespace App\Data\Mail;

final readonly class MailFrame
{
    public function __construct(
        public string $locale,
        public string $direction,
        public string $align,
        public string $logoPath,
        public string $privacyUrl,
        public string $termsUrl,
        public int $year,
    ) {}
}
