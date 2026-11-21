<?php

declare(strict_types=1);

namespace App\Data\Mail;

use App\Enums\Certificates\CertificateLevel;

final readonly class MailFrame
{
    public function __construct(
        public string $locale,
        public string $direction,
        public string $align,
        public string $endAlign,
        public string $logoPath,
        public string $contactUrl,
        public string $privacyUrl,
        public string $termsUrl,
        public int $year,
        public string $sealsDirectory,
    ) {}

    public function sealPath(CertificateLevel $level): string
    {
        return $this->sealsDirectory.'/seal-'.$level->value.'.png';
    }
}
