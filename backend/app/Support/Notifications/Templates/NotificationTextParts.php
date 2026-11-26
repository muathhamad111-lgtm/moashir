<?php

declare(strict_types=1);

namespace App\Support\Notifications\Templates;

use App\Enums\Notifications\NotificationChannel;

/**
 * Which channel each text part feeds. A notice's title and body are its in-app text and, until an administrator
 * writes the e-mail's own subject and body, its e-mail text too; `sms` is the text message. Bespoke e-mail
 * templates (subject, line, button…) feed e-mail only.
 */
final class NotificationTextParts
{
    public const string SMS = 'sms';

    public const string EMAIL_SUBJECT = 'email_subject';

    public const string EMAIL_BODY = 'email_body';

    private const array IN_APP = ['title', 'body', 'admin_title', 'admin_body'];

    /**
     * Parts a core notification keeps as shipped: the button that carries its link.
     */
    private const array FIXED_IN_CORE = ['action'];

    /**
     * The e-mail parts a notice gains, each starting from the in-app part it replaces.
     */
    private const array EMAIL_OF_IN_APP = [self::EMAIL_SUBJECT => 'title', self::EMAIL_BODY => 'body'];

    public static function channelOf(string $part): NotificationChannel
    {
        return match (true) {
            $part === self::SMS => NotificationChannel::Sms,
            in_array($part, self::IN_APP, true) => NotificationChannel::InApp,
            default => NotificationChannel::Email,
        };
    }

    public static function isFixed(string $part, bool $isCore): bool
    {
        return $isCore && in_array($part, self::FIXED_IN_CORE, true);
    }

    /**
     * @param  array<string, array{ar: string, en: string}>  $parts
     * @return array<string, array{ar: string, en: string}>
     */
    public static function withEmailParts(array $parts): array
    {
        if (isset($parts['subject']) || ! isset($parts['title'], $parts['body'])) {
            return $parts;
        }

        foreach (self::EMAIL_OF_IN_APP as $emailPart => $inAppPart) {
            $parts[$emailPart] = $parts[$inAppPart];
        }

        return $parts;
    }
}
