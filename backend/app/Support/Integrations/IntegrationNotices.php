<?php

declare(strict_types=1);

namespace App\Support\Integrations;

use App\Data\Entities\EntityNotice;
use App\Enums\Notifications\NotificationCode;
use App\Support\ValueObjects\LocalizedText;

final class IntegrationNotices
{
    public const string SETTINGS_LINK = '/settings/integrations';

    /**
     * @param  array<string, LocalizedText|string>  $replacements
     */
    public static function for(NotificationCode $code, array $replacements = []): EntityNotice
    {
        $key = 'notifications.integrations.'.$code->value;
        $notice = new EntityNotice(
            $code,
            LocalizedText::fromTranslationWith($key.'.title', $replacements),
            LocalizedText::fromTranslationWith($key.'.body', $replacements),
            self::SETTINGS_LINK,
            true,
            true,
        );

        return $code->sendsMail() ? $notice : $notice->inAppOnly();
    }
}
