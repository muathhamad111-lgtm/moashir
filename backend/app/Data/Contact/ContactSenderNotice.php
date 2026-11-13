<?php

declare(strict_types=1);

namespace App\Data\Contact;

use App\Support\ValueObjects\LocalizedText;

final readonly class ContactSenderNotice
{
    public function __construct(
        public string $reference,
        public LocalizedText $enquiryType,
        public ?string $link,
        public ?string $replyBody = null,
    ) {}
}
