<?php

declare(strict_types=1);

namespace App\Data\Assessments;

final readonly class ReviewNoteChange
{
    public function __construct(public ReviewNoteUnit $unit, public ?string $body) {}

    public function trimmedBody(): string
    {
        return trim($this->body ?? '');
    }
}
