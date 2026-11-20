<?php

declare(strict_types=1);

namespace App\Data\Mail;

use App\Enums\Identity\Locale;
use Symfony\Component\Mime\Email;

final readonly class ComposedMailSample
{
    public function __construct(public string $name, public Locale $locale, public Email $email) {}

    public function label(): string
    {
        return $this->name.'.'.$this->locale->value;
    }
}
