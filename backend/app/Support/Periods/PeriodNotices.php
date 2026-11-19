<?php

declare(strict_types=1);

namespace App\Support\Periods;

use App\Data\Entities\EntityNotice;
use App\Data\Notifications\NoticeText;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Notifications\NotificationCode;
use App\Support\ValueObjects\LocalizedText;

final class PeriodNotices
{
    public const string FOR_ENTITY = 'entity';

    public const string FOR_STAFF = 'staff';

    private const string ROOT = 'notifications.periods.';

    /**
     * @param  array<string, LocalizedText|string>  $replacements
     */
    public static function for(NotificationCode $code, string $key, array $replacements, string $link): EntityNotice
    {
        $root = self::ROOT.$code->value.'.'.$key;

        return EntityNotice::fromText($code, new NoticeText($root, $replacements), $link, AssessmentPath::Accredited);
    }
}
