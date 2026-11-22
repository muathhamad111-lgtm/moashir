<?php

declare(strict_types=1);

namespace App\Support\Sms;

/**
 * How many messages a text costs: GSM-7 text holds 160 characters (153 per part once split); any other
 * character, Arabic included, switches the whole text to UCS-2, which holds 70 (67 per part).
 */
final class SmsLength
{
    public const int MAX_SEGMENTS = 3;

    private const string GSM_BASIC = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

    private const string GSM_EXTENDED = '^{}\\[~]|€';

    private const array GSM = ['single' => 160, 'part' => 153];

    private const array UNICODE = ['single' => 70, 'part' => 67];

    public static function segments(string $text): int
    {
        $characters = mb_str_split($text);
        $isGsm = array_all($characters, static fn (string $character): bool => self::isGsm($character));
        $limits = $isGsm ? self::GSM : self::UNICODE;
        $length = $isGsm ? self::gsmLength($characters) : count($characters);

        if ($length === 0) {
            return 0;
        }

        return $length <= $limits['single'] ? 1 : (int) ceil($length / $limits['part']);
    }

    private static function isGsm(string $character): bool
    {
        return str_contains(self::GSM_BASIC, $character) || str_contains(self::GSM_EXTENDED, $character);
    }

    /**
     * @param  list<string>  $characters
     */
    private static function gsmLength(array $characters): int
    {
        return array_sum(array_map(static fn (string $character): int => str_contains(self::GSM_EXTENDED, $character) ? 2 : 1, $characters));
    }
}
