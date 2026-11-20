<?php

declare(strict_types=1);

namespace App\Data\Mail;

use App\Enums\Notifications\MailDetailTone;

/**
 * One label and value of the e-mail's details card, already in the recipient's language.
 */
final readonly class DetailRow
{
    public function __construct(public string $label, public string $value, public MailDetailTone $tone) {}

    public function isReference(): bool
    {
        return $this->tone === MailDetailTone::Reference;
    }
}
