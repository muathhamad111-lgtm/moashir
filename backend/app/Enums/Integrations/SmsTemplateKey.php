<?php

declare(strict_types=1);

namespace App\Enums\Integrations;

enum SmsTemplateKey: string
{
    case OtpCode = 'otp_code';

    /**
     * @return list<string>
     */
    public function placeholders(): array
    {
        return match ($this) {
            self::OtpCode => ['code', 'minutes'],
        };
    }

    /**
     * @return list<string>
     */
    public function requiredPlaceholders(): array
    {
        return match ($this) {
            self::OtpCode => ['code'],
        };
    }

    /**
     * @return array<string, string>
     */
    public function longestSample(): array
    {
        return match ($this) {
            self::OtpCode => ['code' => '00000000', 'minutes' => '15'],
        };
    }

    public function singleSegmentOnly(): bool
    {
        return match ($this) {
            self::OtpCode => true,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
