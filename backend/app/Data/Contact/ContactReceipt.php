<?php

declare(strict_types=1);

namespace App\Data\Contact;

use Carbon\CarbonInterface;

final readonly class ContactReceipt
{
    public function __construct(public string $reference, public CarbonInterface $createdAt, public ?string $messageId = null) {}
}
