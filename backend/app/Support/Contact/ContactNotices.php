<?php

declare(strict_types=1);

namespace App\Support\Contact;

use App\Data\Entities\EntityNotice;
use App\Enums\Notifications\NotificationCode;
use App\Models\Contact\ContactMessage;
use App\Support\ValueObjects\LocalizedText;

final class ContactNotices
{
    private const string ROOT = 'notifications.contact.';

    public static function forHandlers(NotificationCode $code, ContactMessage $message): EntityNotice
    {
        $replacements = ['reference' => $message->reference, 'type' => self::enquiryTypeOf($message)];

        return new EntityNotice(
            $code,
            LocalizedText::fromTranslationWith(self::ROOT.$code->value.'.title', $replacements),
            LocalizedText::fromTranslationWith(self::ROOT.$code->value.'.body', $replacements),
            ContactLinks::staff($message),
            true,
            true,
        );
    }

    public static function enquiryTypeOf(ContactMessage $message): LocalizedText
    {
        return LocalizedText::fromTranslation('contact.enquiry_types.'.$message->enquiry_type->value);
    }
}
