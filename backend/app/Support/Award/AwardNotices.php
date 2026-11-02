<?php

declare(strict_types=1);

namespace App\Support\Award;

use App\Data\Entities\EntityNotice;
use App\Enums\Notifications\NotificationCode;
use App\Models\Award\AwardCycle;
use App\Support\ValueObjects\LocalizedText;
use Carbon\CarbonInterface;

final class AwardNotices
{
    public const string FOR_ENTITY = 'entity';

    public const string FOR_STAFF = 'staff';

    private const string ROOT = 'notifications.award.';

    /**
     * @param  array<string, LocalizedText|string>  $extra
     */
    public static function for(NotificationCode $code, string $audience, AwardCycle $cycle, string $link, array $extra = []): EntityNotice
    {
        $replacements = [
            'cycle' => $cycle->name,
            'submission_end' => self::moment($cycle->submission_end),
            ...$extra,
        ];
        $key = self::ROOT.$code->value.'.'.$audience;

        return new EntityNotice(
            $code,
            LocalizedText::fromTranslationWith($key.'.title', $replacements),
            LocalizedText::fromTranslationWith($key.'.body', $replacements),
            $link,
            true,
            true,
        );
    }

    public static function moment(CarbonInterface $moment): string
    {
        return $moment->copy()->setTimezone(config()->string('app.display_timezone'))->format('Y-m-d H:i');
    }
}
