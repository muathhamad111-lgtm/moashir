<?php

declare(strict_types=1);

namespace App\Support\Contact;

use App\Data\Entities\EntityNotice;
use App\Data\Notifications\NoticeText;
use App\Enums\Notifications\NotificationCode;
use App\Models\Contact\ContactMessage;
use App\Support\ValueObjects\LocalizedText;

final class ContactNotices
{
    private const string ROOT = 'notifications.contact.';

    public static function forHandlers(NotificationCode $code, ContactMessage $message): EntityNotice
    {
        $replacements = ['reference' => $message->reference, 'type' => self::enquiryTypeOf($message)];

        return EntityNotice::fromText($code, new NoticeText(self::ROOT.$code->value, $replacements), ContactLinks::staff($message));
    }

    public static function enquiryTypeOf(ContactMessage $message): LocalizedText
    {
        return LocalizedText::fromTranslation('contact.enquiry_types.'.$message->enquiry_type->value);
    }
}
