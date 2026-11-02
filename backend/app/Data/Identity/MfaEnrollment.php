<?php

declare(strict_types=1);

namespace App\Data\Identity;

final readonly class MfaEnrollment
{
    public function __construct(public string $qrCodeSvg, public string $manualEntryKey) {}
}
