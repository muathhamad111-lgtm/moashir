<?php

declare(strict_types=1);

namespace App\Services\Identity;

final readonly class OtpCodeGenerator
{
    public function __construct(private AuthSettings $settings) {}

    public function generate(): string
    {
        $length = $this->settings->otpLength();

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }
}
