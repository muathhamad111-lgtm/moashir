<?php

declare(strict_types=1);

namespace App\Data\Mail;

use App\Enums\Assessments\CompletionItemKind;
use App\Support\ValueObjects\LocalizedText;

/**
 * One item the reviewer asked the entity to complete: the sub-criterion's code and name (null when it no longer
 * exists in the request's criteria version), what to complete for it and the reviewer's note.
 */
final readonly class RequestedItem
{
    public function __construct(
        public string $code,
        public ?LocalizedText $name,
        public CompletionItemKind $kind,
        public ?string $note,
    ) {}

    public function hasNote(): bool
    {
        return $this->note !== null && trim($this->note) !== '';
    }
}
