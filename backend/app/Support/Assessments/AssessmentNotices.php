<?php

declare(strict_types=1);

namespace App\Support\Assessments;

use App\Data\Entities\EntityNotice;
use App\Data\Notifications\NoticeText;
use App\Enums\Notifications\NotificationCode;
use App\Support\ValueObjects\LocalizedText;

final class AssessmentNotices
{
    /**
     * @param  array<string, LocalizedText|string>  $replacements
     */
    public static function for(NotificationCode $code, array $replacements, string $link): EntityNotice
    {
        return self::fromKey($code, 'notifications.assessments.'.$code->value, $replacements, $link);
    }

    /**
     * @param  array<string, LocalizedText|string>  $replacements
     */
    public static function fromKey(NotificationCode $code, string $key, array $replacements, string $link): EntityNotice
    {
        return EntityNotice::fromText($code, new NoticeText($key, $replacements), $link);
    }
}
