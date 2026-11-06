<?php

declare(strict_types=1);

namespace App\Enums\Settings;

enum PathEntityText: string
{
    case Intro = 'intro';
    case Instructions = 'instructions';
    case AfterSubmission = 'after_submission';

    public const int MAX_LENGTH = 2000;

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
