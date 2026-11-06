<?php

declare(strict_types=1);

namespace App\Enums\Notifications;

enum MailReason: string
{
    case Account = 'account';
    case Invitation = 'invitation';
    case AddressVerification = 'address_verification';
    case LinkedAddress = 'linked_address';
    case ContactForm = 'contact_form';
    case SecurityMailbox = 'security_mailbox';
}
