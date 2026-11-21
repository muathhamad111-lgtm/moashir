<?php

declare(strict_types=1);

namespace App\Support\Notifications\Templates;

use App\Enums\Notifications\NotificationCode;

final class NotificationTextKeys
{
    /**
     * Text groups whose keys carry no code segment, mapped to the code they are sent under.
     */
    private const array ALIASES = [
        'staff_invited' => 'USR-01',
        'account_locked' => 'USR-02',
        'account_status' => 'USR-03',
        'mfa_reset' => 'USR-03',
        'password_reset' => 'USR-04',
        'super_admin_email_changed' => 'USR-05',
        'otp' => 'REG-02',
        'contact.reply' => 'CNT-02',
        'contact.acknowledgement' => 'CNT-03',
        'payments.issues' => 'INT-04',
        'files.scan_abandoned' => 'INT-04',
        'assessments.criteria_update_stalled' => 'INT-05',
        'assessments.paid_request_opening_failed' => 'INT-05',
        'assessments.accredited_opening_failed' => 'INT-05',
        'files.quarantined' => 'INT-05',
        'files.infected_upload' => 'INT-05',
    ];

    public static function codeOf(string $key): ?NotificationCode
    {
        foreach (explode('.', $key) as $segment) {
            $code = NotificationCode::tryFrom($segment);

            if ($code instanceof NotificationCode) {
                return $code;
            }
        }

        return self::aliasOf($key);
    }

    private static function aliasOf(string $key): ?NotificationCode
    {
        foreach (self::ALIASES as $prefix => $code) {
            if ($key === $prefix || str_starts_with($key, $prefix.'.')) {
                return NotificationCode::from($code);
            }
        }

        return null;
    }
}
