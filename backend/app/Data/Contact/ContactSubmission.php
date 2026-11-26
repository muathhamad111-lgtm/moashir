<?php

declare(strict_types=1);

namespace App\Data\Contact;

use App\Enums\Contact\ContactEnquiryType;
use App\Enums\Identity\Locale;
use App\Support\ValueObjects\PhoneNumber;

final readonly class ContactSubmission
{
    public function __construct(
        public ?ContactIdentity $guestIdentity,
        public ?PhoneNumber $mobile,
        public ContactEnquiryType $enquiryType,
        public string $message,
        public Locale $locale,
        public bool $isAutomated,
    ) {}
}
