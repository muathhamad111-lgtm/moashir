<?php

declare(strict_types=1);

namespace App\Support\Notifications\Templates;

use App\Enums\Notifications\NotificationCode;

final readonly class NotificationTextTemplate
{
    /**
     * @param  array<string, array{ar: string, en: string}>  $parts  default text per part (title, body, subject, line…)
     * @param  list<string>  $placeholders
     */
    public function __construct(
        public string $key,
        public NotificationCode $code,
        public array $parts,
        public array $placeholders,
    ) {}

    public function partKey(string $part): string
    {
        return $this->key.'.'.$part;
    }

    /**
     * What sets this text apart from the other texts of the same type: the audience or state after the code
     * («entity», «staff», «continued.entity»), empty when the type has one text, or the whole group key for
     * texts filed without a code («account_status.suspended», «files.quarantined»).
     */
    public function variant(): string
    {
        $marker = '.'.$this->code->value;
        $position = strpos($this->key, $marker.'.');

        if ($position !== false) {
            return substr($this->key, $position + strlen($marker) + 1);
        }

        return str_ends_with($this->key, $marker) ? '' : $this->key;
    }
}
