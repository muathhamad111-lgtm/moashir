<?php

declare(strict_types=1);

namespace App\Enums\Integrations;

enum SmsTemplateKey: string
{
    case OtpCode = 'otp_code';

    private const int LONGEST_ARABIC_MINUTES = 1;

    private const int LONGEST_ENGLISH_MINUTES = 15;

    /**
     * @return list<string>
     */
    public function placeholders(): array
    {
        return match ($this) {
            self::OtpCode => ['code', 'validity', 'minutes'],
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
    public function longestSample(string $locale): array
    {
        return match ($this) {
            self::OtpCode => [
                'code' => '00000000',
                'validity' => trans_choice('quantities.minutes', $locale === 'en' ? self::LONGEST_ENGLISH_MINUTES : self::LONGEST_ARABIC_MINUTES, [], $locale),
                'minutes' => '15',
            ],
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
