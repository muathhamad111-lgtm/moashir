<?php

declare(strict_types=1);

namespace App\Support\Contact;

use App\Models\Contact\ContactMessage;

final class ContactLinks
{
    private const string STAFF_MESSAGES = '/admin/contact-messages/';

    private const string SENDER_MESSAGES = '/messages/';

    public static function staff(ContactMessage $message): string
    {
        return self::STAFF_MESSAGES.$message->public_id;
    }

    public static function sender(ContactMessage $message): ?string
    {
        return $message->isLinked() ? self::SENDER_MESSAGES.$message->public_id : null;
    }
}
